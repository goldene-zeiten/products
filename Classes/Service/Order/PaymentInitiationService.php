<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Service\Order\Exception\PaymentFailedException;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class PaymentInitiationService
{
    public function __construct(
        private readonly PaymentContextFactory $paymentContextFactory,
        private readonly PaymentTransactionRepository $paymentTransactionRepository,
        private readonly PersistenceManagerInterface $persistenceManager
    ) {}

    public function initiate(Order $order, PaymentMethodInterface $paymentMethod): PaymentResult
    {
        $paymentContext = $this->paymentContextFactory->createFromOrder($order);
        $paymentResult = $paymentMethod->initiate($order, $paymentContext);
        $this->persistPaymentTransaction($order, $paymentMethod, $paymentResult);

        if ($paymentResult->getState() === PaymentResultState::FAILED) {
            throw new PaymentFailedException($paymentResult->getFailureReason(), 1751751042);
        }

        return $paymentResult;
    }

    /**
     * Reuses an existing not-yet-approved transaction for the same order/method instead of
     * inserting a duplicate row - a resubmitted checkout/double-click/timeout-retry must not
     * pollute payment reporting/reconciliation with multiple rows for the same attempt.
     */
    private function persistPaymentTransaction(Order $order, PaymentMethodInterface $paymentMethod, PaymentResult $paymentResult): void
    {
        $orderUid = $order->getUid() ?? 0;
        $existing = $this->paymentTransactionRepository->findOneNotYetApproved($orderUid, $paymentMethod->getIdentifier());
        $transaction = $existing ?? new PaymentTransaction();
        $transaction->setOrderUid($orderUid);
        $transaction->setPaymentMethod($paymentMethod->getIdentifier());
        $transaction->setState($paymentResult->getState()->value);
        $transaction->setAmount($order->getTotalGross()->getCents());
        $transaction->setCurrency($order->getCurrency());
        $transaction->setRawData($paymentResult->getRawData());

        if ($existing !== null) {
            $this->paymentTransactionRepository->update($transaction);
        } else {
            $this->paymentTransactionRepository->add($transaction);
        }
        $this->persistenceManager->persistAll();
    }
}
