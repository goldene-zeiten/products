<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Core\Domain\Repository\PaymentTransactionRepository;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Keeps the payment transaction - the log of one payment of an order - in step with what its method reported:
 * the row the payment started with is updated to the outcome and the gateway's reference, and a result the
 * transaction already carries, replayed by a second notification, changes it in place instead of adding a row.
 */
final class PaymentTransactionRecorder
{
    public function __construct(
        private readonly PaymentTransactionRepository $paymentTransactionRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
    ) {}

    public function record(Order $order, PaymentResult $paymentResult): void
    {
        $orderUid = $order->getUid() ?? 0;
        $paymentMethod = $order->getPaymentMethod();
        $existing = $this->paymentTransactionRepository->findOneByExternalId($paymentMethod, $paymentResult->getExternalId())
            ?? $this->paymentTransactionRepository->findOneNotYetApproved($orderUid, $paymentMethod);
        $transaction = $existing ?? new PaymentTransaction();
        $transaction->setOrderUid($orderUid);
        $transaction->setPaymentMethod($paymentMethod);
        $transaction->setState($paymentResult->getState()->value);
        $transaction->setAmount($order->getTotalGross()->getCents());
        $transaction->setCurrency($order->getCurrency());
        if ($paymentResult->getExternalId() !== '') {
            $transaction->setExternalId($paymentResult->getExternalId());
        }
        if ($paymentResult->getRawData() !== []) {
            $transaction->setRawData($paymentResult->getRawData());
        }
        if ($existing !== null) {
            $this->paymentTransactionRepository->update($transaction);
        } else {
            $this->paymentTransactionRepository->add($transaction);
        }
        $this->persistenceManager->persistAll();
    }
}
