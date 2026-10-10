<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\OrderAccessDeniedException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderNotRefundableException;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Payment\RefundablePaymentMethodInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Refunds an order's full amount from the backend order module. Every check - the user's write
 * permission, the payment status, the payment method's refund support - runs before the gateway is
 * called, because the gateway call moves money and cannot be undone by a failing status write.
 */
final class OrderRefundService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OrderAccessGuard $accessGuard,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly OrderDataFactory $orderDataFactory,
        private readonly OrderStatusWriter $orderStatusWriter,
    ) {}

    /**
     * @throws OrderAccessDeniedException
     * @throws OrderNotRefundableException
     */
    public function refund(int $orderUid, BackendUserAuthentication $backendUser): void
    {
        $paymentMethod = $this->assertRefundable($orderUid, $backendUser);
        $order = $this->orderDataFactory->build($orderUid);
        if ($order === null) {
            return;
        }
        $result = $paymentMethod->refund($order, $order->totalGross);
        if ($result->getState() === PaymentResultState::COMPLETED) {
            $this->orderStatusWriter->changePaymentStatus($orderUid, PaymentStatus::REFUNDED, $backendUser);
        }
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
        if ($status === null || !$status->canTransitionTo(PaymentStatus::REFUNDED)) {
            throw new OrderNotRefundableException(
                sprintf('Order %d cannot be refunded from payment status "%s".', $orderUid, (string)$row['payment_status']),
                1791663248
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
        $row = $queryBuilder->select('pid', 'payment_status', 'payment_method')
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }
}
