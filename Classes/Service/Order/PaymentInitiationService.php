<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\PaymentTransaction;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Core\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Service\Order\Exception\PaymentFailedException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class PaymentInitiationService
{
    public function __construct(
        private readonly PaymentContextFactory $paymentContextFactory,
        private readonly PaymentTransactionRepository $paymentTransactionRepository,
        private readonly OrderRepository $orderRepository,
        private readonly PersistenceManagerInterface $persistenceManager
    ) {}

    public function initiate(Order $order, PaymentMethodInterface $paymentMethod, ServerRequestInterface $request): PaymentResult
    {
        $paymentContext = $this->paymentContextFactory->createFromOrder($order, $request);
        $paymentResult = $paymentMethod->initiate($order, $paymentContext);
        // Explicit update() needed—Extbase won't auto-flush fetched+mutated entities.
        $this->orderRepository->update($order);
        $this->persistPaymentTransaction($order, $paymentMethod, $paymentResult);

        if ($paymentResult->getState() === PaymentResultState::FAILED) {
            throw new PaymentFailedException($paymentResult->getFailureReason(), 1751751042);
        }

        return $paymentResult;
    }

    /**
     * Reuse existing unapproved transaction to prevent duplicate rows on retry.
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
