<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Event\AfterOrderFinalizedEvent;
use GoldeneZeiten\Products\Core\Event\BeforeOrderFinalizedEvent;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Checkout\CheckoutService;
use GoldeneZeiten\Products\Core\Service\Checkout\CheckoutStateStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Applies what a payment reported to its order. Call it for every result a payment method produces - at
 * placement, on the customer's return and on each gateway notification; it is safe to call repeatedly and
 * concurrently for the same order.
 *
 * - A completed or pending payment accepts the order: the first such call confirms it and announces it
 *   {@see AfterOrderFinalizedEvent}; every call clears the calling customer's basket and checkout.
 * - A failed payment cancels an order that is not paid yet and keeps the customer's basket for another try.
 * - Every call applies the reported payment status where the current one allows it.
 */
final class OrderFinalizationService
{
    public function __construct(
        private readonly OrderStatusManager $orderStatusManager,
        private readonly OrderRepository $orderRepository,
        private readonly BasketService $basketService,
        private readonly CheckoutService $checkoutService,
        private readonly CheckoutStateStore $checkoutStateStore,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {}

    public function finalize(Order $order, PaymentResult $paymentResult, ServerRequestInterface $request): void
    {
        match ($paymentResult->getState()) {
            PaymentResultState::COMPLETED, PaymentResultState::PENDING => $this->accept($order, $paymentResult, $request),
            PaymentResultState::FAILED => $this->reject($order, $paymentResult->getFailureReason()),
            PaymentResultState::REDIRECT_REQUIRED => null,
        };
    }

    /**
     * The payment never came about - declined, abandoned at the gateway, or never started - so the order is
     * cancelled, which also returns its stock. A failure reported for a payment that is already paid is a
     * stale notification and changes nothing.
     */
    public function reject(Order $order, string $reason): void
    {
        if (!$this->orderStatusManager->claimPaymentTransition($order, PaymentStatus::FAILED)) {
            return;
        }
        $this->orderStatusManager->claimTransition($order, OrderStatus::CANCELLED, $reason !== '' ? $reason : 'Payment failed.');
        $this->persist($order);
    }

    private function accept(Order $order, PaymentResult $paymentResult, ServerRequestInterface $request): void
    {
        if ($this->orderStatusManager->claimTransition($order, OrderStatus::CONFIRMED)) {
            $this->eventDispatcher->dispatch(new BeforeOrderFinalizedEvent($order, $paymentResult));
            $this->orderStatusManager->claimPaymentTransition($order, $paymentResult->getPaymentStatus());
            $this->persist($order);
            $this->clearCustomerCheckout($request);
            $this->eventDispatcher->dispatch(new AfterOrderFinalizedEvent($order));
            return;
        }

        if ($order->getStatus() === OrderStatus::CANCELLED) {
            $this->recordPaymentForCancelledOrder($order, $paymentResult);
            return;
        }

        $this->orderStatusManager->claimPaymentTransition($order, $paymentResult->getPaymentStatus());
        $this->persist($order);
        $this->clearCustomerCheckout($request);
    }

    /**
     * Money that arrives for an order which was already cancelled - the customer abandoned the payment, then
     * completed it after all - is recorded so the merchant sees it and can refund it; the order stays
     * cancelled.
     */
    private function recordPaymentForCancelledOrder(Order $order, PaymentResult $paymentResult): void
    {
        if ($paymentResult->getPaymentStatus() !== PaymentStatus::PAID
            || !$this->orderStatusManager->claimPaymentTransition($order, PaymentStatus::PAID)
        ) {
            return;
        }
        $this->persist($order);
        $this->logger->error(
            sprintf('Order %d was paid after it had been cancelled; the payment needs to be refunded.', (int)$order->getUid()),
            ['externalId' => $paymentResult->getExternalId()]
        );
    }

    private function persist(Order $order): void
    {
        $this->orderRepository->update($order);
        $this->persistenceManager->persistAll();
    }

    /**
     * Idempotent: the customer returning after the gateway's webhook already accepted the order still has
     * their basket and checkout cleared, and a webhook - which has no customer session - clears nothing.
     */
    private function clearCustomerCheckout(ServerRequestInterface $request): void
    {
        $this->basketService->clear($request);
        $this->checkoutService->clearCheckoutSession($request);
        $this->checkoutStateStore->clear($request);
    }
}
