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
use GoldeneZeiten\Products\Service\Checkout\CheckoutStateStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderFinalizationService
{
    public function __construct(
        private readonly OrderStatusManager $orderStatusManager,
        private readonly BasketService $basketService,
        private readonly CheckoutService $checkoutService,
        private readonly CheckoutStateStore $checkoutStateStore,
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
        $this->checkoutStateStore->clear($request);

        $this->eventDispatcher->dispatch(new AfterOrderFinalizedEvent($order));
    }

    /**
     * Guard against duplicate finalization (retry, async webhook, etc.).
     */
    private function isAlreadyFinalized(Order $order): bool
    {
        return $order->getStatus() !== OrderStatus::NEW && $order->getStatus() !== OrderStatus::PENDING;
    }
}
