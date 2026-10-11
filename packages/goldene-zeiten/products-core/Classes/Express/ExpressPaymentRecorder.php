<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Core\Domain\Repository\PaymentTransactionRepository;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Binds the gateway's payment reference of an express payment to the order it paid, so a later gateway
 * notification that only names the reference (a PaymentIntent or capture id) resolves to that order. Look
 * the order up with {@see PaymentTransactionRepository::findOneByExternalId()} under the provider identifier.
 */
final class ExpressPaymentRecorder
{
    public function __construct(
        private readonly PaymentTransactionRepository $paymentTransactionRepository,
        private readonly PersistenceManagerInterface $persistenceManager
    ) {}

    public function record(Order $order, ExpressCheckoutProviderInterface $provider, PaymentResult $settled): void
    {
        if ($settled->getExternalId() === '') {
            return;
        }
        $transaction = new PaymentTransaction();
        $transaction->setOrderUid((int)$order->getUid());
        $transaction->setPaymentMethod($provider->getIdentifier());
        $transaction->setExternalId($settled->getExternalId());
        $transaction->setState($settled->getState()->value);
        $transaction->setAmount($order->getTotalGross()->getCents());
        $transaction->setCurrency($order->getCurrency());
        $transaction->setRawData($settled->getRawData());
        $this->paymentTransactionRepository->add($transaction);
        $this->persistenceManager->persistAll();
    }
}
