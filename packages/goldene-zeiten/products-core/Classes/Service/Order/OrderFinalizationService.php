<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Event\AfterOrderFinalizedEvent;
use GoldeneZeiten\Products\Core\Event\BeforeOrderFinalizedEvent;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Checkout\CheckoutService;
use GoldeneZeiten\Products\Core\Service\Checkout\CheckoutStateStore;
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
