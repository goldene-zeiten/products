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

    private function persistPaymentTransaction(Order $order, PaymentMethodInterface $paymentMethod, PaymentResult $paymentResult): void
    {
        $transaction = new PaymentTransaction();
        $transaction->setOrderUid($order->getUid() ?? 0);
        $transaction->setPaymentMethod($paymentMethod->getIdentifier());
        $transaction->setState($paymentResult->getState()->value);
        $transaction->setAmount($order->getTotalGross()->getCents());
        $transaction->setCurrency($order->getCurrency());
        $transaction->setRawData($paymentResult->getRawData());
        $this->paymentTransactionRepository->add($transaction);
        $this->persistenceManager->persistAll();
    }
}
