<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Event\AfterOrderFinalizedEvent;
use GoldeneZeiten\Products\Event\BeforeOrderFinalizedEvent;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\Checkout\CheckoutService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderFinalizationService
{
    public function __construct(
        private readonly OrderStatusManager $orderStatusManager,
        private readonly BasketService $basketService,
        private readonly CheckoutService $checkoutService,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function finalize(Order $order, PaymentResult $paymentResult, ServerRequestInterface $request): void
    {
        if ($this->isAlreadyFinalized($order)) {
            return;
        }

        $this->eventDispatcher->dispatch(new BeforeOrderFinalizedEvent($order, $paymentResult));

        $this->orderStatusManager->transition($order, OrderStatus::CONFIRMED);
        $this->orderStatusManager->transitionPayment($order, $paymentResult->getPaymentStatus());
        $this->persistenceManager->persistAll();

        $this->basketService->clear($request);
        $this->checkoutService->clearCheckoutSession($request);

        $this->eventDispatcher->dispatch(new AfterOrderFinalizedEvent($order));
    }

    /**
     * A repeat call for the same order (a payment gateway's synchronous return firing alongside
     * its async webhook, a browser retry, ...) must not re-dispatch events/re-clear the
     * basket/re-send emails a second time. Keyed off the order's persisted status rather than any
     * "has finalize run" flag, so a genuinely not-yet-finalized order is never affected: only
     * NEW/PENDING orders are still eligible to be finalized at all.
     */
    private function isAlreadyFinalized(Order $order): bool
    {
        return $order->getStatus() !== OrderStatus::NEW && $order->getStatus() !== OrderStatus::PENDING;
    }
}
