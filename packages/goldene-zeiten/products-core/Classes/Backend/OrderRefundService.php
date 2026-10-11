<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\OrderAccessDeniedException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderNotRefundableException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderRefundFailedException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderRefundWithheldException;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Payment\RefundablePaymentMethodInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Refunds an order from the backend order module: the full amount, or after a partial withdrawal what
 * was withdrawn. Every check - the user's write permission, the payment status, the payment method's
 * refund support, the trader's right to withhold the refund for goods still on their way back (Art. 13(3)
 * Directive 2011/83/EU) - runs before the gateway is called, because the gateway call moves money and
 * cannot be undone by a failing status write.
 *
 * The refund is claimed on the order row before the gateway is called ({@see OrderRefundClaim}), so
 * simultaneous requests cannot both refund. A gateway that only accepts the refund leaves the order
 * "refund pending" - not refundable again - until the gateway's notification or the backend completes it;
 * a gateway that declines or fails releases the claim.
 */
final class OrderRefundService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OrderAccessGuard $accessGuard,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly OrderDataFactory $orderDataFactory,
        private readonly OrderStatusWriter $orderStatusWriter,
        private readonly OrderRefundClaim $refundClaim,
    ) {}

    /**
     * @throws OrderAccessDeniedException
     * @throws OrderNotRefundableException
     * @throws OrderRefundWithheldException
     * @throws OrderRefundFailedException
     */
    public function refund(int $orderUid, BackendUserAuthentication $backendUser): void
    {
        $paymentMethod = $this->assertRefundable($orderUid, $backendUser);
        $order = $this->orderDataFactory->build($orderUid);
        if ($order === null) {
            return;
        }
        if (!$this->refundClaim->claim($orderUid)) {
            throw new OrderNotRefundableException(
                sprintf('Order %d is already being refunded.', $orderUid),
                1791729503
            );
        }
        try {
            $result = $paymentMethod->refund($order, $this->refundAmount($orderUid, $order->totalGross));
        } catch (\Throwable $exception) {
            $this->refundClaim->release($orderUid);
            throw $exception;
        }
        $this->applyResult($orderUid, $result, $backendUser);
    }

    private function applyResult(int $orderUid, PaymentResult $result, BackendUserAuthentication $backendUser): void
    {
        if ($result->getState() === PaymentResultState::COMPLETED) {
            $this->orderStatusWriter->changePaymentStatus($orderUid, PaymentStatus::REFUNDED, $backendUser);
            return;
        }
        if ($result->getState() === PaymentResultState::PENDING) {
            return;
        }
        $this->refundClaim->release($orderUid);
        throw new OrderRefundFailedException(
            sprintf('The payment gateway did not refund order %d: %s', $orderUid, $result->getFailureReason()),
            1791729505
        );
    }

    private function assertRefundable(int $orderUid, BackendUserAuthentication $backendUser): RefundablePaymentMethodInterface
    {
        $row = $this->fetchOrderRow($orderUid);
        if ($row === null || !$this->accessGuard->mayWrite((int)$row['pid'], $backendUser)) {
            throw new OrderAccessDeniedException(
                sprintf('The current backend user may not change order %d.', $orderUid),
                1791663246
            );
        }
        $status = PaymentStatus::tryFrom((string)$row['payment_status']);
        if ($status === null || !$status->isRefundable()) {
            throw new OrderNotRefundableException(
                sprintf('Order %d cannot be refunded from payment status "%s".', $orderUid, (string)$row['payment_status']),
                1791663248
            );
        }
        if (OrderStatus::tryFrom((string)$row['status']) === OrderStatus::WITHDRAWN && (int)$row['return_proof_at'] === 0) {
            throw new OrderRefundWithheldException(
                sprintf('The refund of order %d is withheld until the withdrawn goods are returned or proven sent back.', $orderUid),
                1791730329
            );
        }
        $paymentMethod = $this->paymentMethodRegistry->get((string)$row['payment_method']);
        if (!$paymentMethod instanceof RefundablePaymentMethodInterface) {
            throw new OrderNotRefundableException(
                sprintf('Payment method "%s" of order %d does not support refunds.', (string)$row['payment_method'], $orderUid),
                1791663271
            );
        }
        return $paymentMethod;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchOrderRow(int $orderUid): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder->select('pid', 'payment_status', 'payment_method', 'status', 'return_proof_at')
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * The whole order's amount, unless the customer withdrew from only part of it: then the gross of
     * the withdrawn lines.
     */
    private function refundAmount(int $orderUid, Money $totalGross): Money
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_orderitem');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $result = $queryBuilder->select('line_total_gross', 'withdrawn_at')
            ->from('tx_products_domain_model_orderitem')
            ->where($queryBuilder->expr()->eq('parent_order', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery();
        $itemCount = 0;
        $withdrawnCount = 0;
        $withdrawnGross = 0;
        while ($row = $result->fetchAssociative()) {
            $itemCount++;
            if ((int)$row['withdrawn_at'] > 0) {
                $withdrawnCount++;
                $withdrawnGross += (int)$row['line_total_gross'];
            }
        }
        return $withdrawnCount === 0 || $withdrawnCount === $itemCount ? $totalGross : Money::fromCents($withdrawnGross);
    }
}
