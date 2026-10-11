<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderTotals;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Event\BeforeOrderPlacedEvent;
use GoldeneZeiten\Products\Core\Express\Exception\EmptyExpressBasketException;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressAmountMismatchException;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressOrderExceptionInterface;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressOrderFailedException;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementVetoedException;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Core\Service\Order\OrderFinalizationService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Turns a confirmed express wallet payment into an order. It is the express counterpart of
 * {@see OrderPlacementService}: the address comes from the wallet rather than the form and there is no
 * interactive terms step.
 *
 * Call {@see quote()} for the amount a wallet has to authorize, and {@see place()} to create the order:
 * it creates the order first, so shipping, stock and integrator vetoes are decided before any money moves,
 * then has the provider's {@see ExpressSettlementInterface} charge exactly the order total and refuses to
 * mark the order paid for any other amount. A payment settled for an order that then fails is refunded.
 *
 * A payment that is accepted but not final yet (processing, held for review) places the order with its
 * payment pending, exactly as the redirect flow does; the gateway's webhook later settles or rejects it by
 * the payment reference {@see ExpressPaymentRecorder} bound to the order.
 *
 * It reuses the exact {@see OrderCreationService} and {@see OrderFinalizationService} normal checkout runs
 * on, in one transaction, so stock, numbering, loyalty and basket clearing behave identically - the single
 * source of truth the express seam is built around. Unlike the shipping-rate callback it runs in a normal
 * in-page request, so the full configuration is available.
 */
#[Autoconfigure(public: true)]
final readonly class ExpressOrderService
{
    public function __construct(
        private PriceQuoteService $priceQuoteService,
        private ProductsConfigurationFactory $configurationFactory,
        private OrderCreationService $orderCreationService,
        private OrderFinalizationService $orderFinalizationService,
        private ExpressPaymentRecorder $paymentRecorder,
        private ConnectionPool $connectionPool,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger
    ) {}

    /**
     * What placing the order for this basket, address and shipping option charges - computed by the code
     * that creates the order, so a wallet is asked to authorize the amount that is later checked.
     */
    public function quote(
        ServerRequestInterface $request,
        ExpressCheckoutProviderInterface $provider,
        BasketViewModel $liveBasketViewModel,
        Address $address,
        string $shippingOptionKey
    ): OrderTotals {
        return $this->orderCreationService->quote(
            $request,
            $this->quotedBasket($request, $liveBasketViewModel),
            new CheckoutSelections($shippingOptionKey),
            $address,
            new ExpressPaymentDescriptor($provider)
        );
    }

    /**
     * @throws ExpressOrderExceptionInterface when the order could not be placed; nothing is left charged
     */
    public function place(
        ServerRequestInterface $request,
        ExpressCheckoutProviderInterface $provider,
        BasketViewModel $liveBasketViewModel,
        Address $address,
        string $shippingOptionKey,
        ExpressSettlementInterface $settlement
    ): Order {
        $order = $this->createOrder(
            $request,
            $this->quotedBasket($request, $liveBasketViewModel),
            $address,
            $shippingOptionKey,
            new ExpressPaymentDescriptor($provider)
        );
        $settled = null;
        try {
            $settled = $settlement->settle($order);
            $this->assertSettledAmount($order, $settled);
            $this->paymentRecorder->record($order, $provider, $settled);
            $this->orderFinalizationService->finalize($order, $settled, $request);
        } catch (\Throwable $exception) {
            $this->abort($settlement, $settled, $order, $exception);
        }

        return $order;
    }

    private function quotedBasket(ServerRequestInterface $request, BasketViewModel $liveBasketViewModel): BasketViewModel
    {
        $basketViewModel = $this->priceQuoteService->resolve($request, $liveBasketViewModel, $this->configurationFactory->create($request));
        if ($basketViewModel->getItems() === []) {
            throw new EmptyExpressBasketException('Cannot place an express order for an empty basket.', 1784220769);
        }

        return $basketViewModel;
    }

    /**
     * Creates the order atomically and commits it before any money moves, so the stock and number-range
     * locks are not held across the gateway round-trip.
     */
    private function createOrder(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        Address $address,
        string $shippingOptionKey,
        ExpressPaymentDescriptor $descriptor
    ): Order {
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_order');
        $connection->beginTransaction();
        try {
            $this->dispatchBeforeOrderPlaced($request, $basketViewModel, $address, $descriptor);
            $order = $this->orderCreationService->create($request, $basketViewModel, new CheckoutSelections($shippingOptionKey), $address, $descriptor);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->abort(null, null, null, $exception);
        }

        return $order;
    }

    private function dispatchBeforeOrderPlaced(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        Address $address,
        ExpressPaymentDescriptor $descriptor
    ): void {
        $event = new BeforeOrderPlacedEvent($request, $basketViewModel, $address, $descriptor);
        $this->eventDispatcher->dispatch($event);
        if ($event->isVetoed()) {
            throw new OrderPlacementVetoedException($event->getVetoReason(), 1791724119);
        }
    }

    private function assertSettledAmount(Order $order, PaymentResult $settled): void
    {
        $amount = $settled->getSettledAmount();
        if ($amount === null || $amount->getCents() !== $order->getTotalGross()->getCents()) {
            throw new ExpressAmountMismatchException(
                sprintf(
                    'The wallet settled %s but the order totals %s.',
                    $amount?->getDecimalString() ?? 'an unknown amount',
                    $order->getTotalGross()->getDecimalString()
                ),
                1791724084
            );
        }
    }

    private function abort(?ExpressSettlementInterface $settlement, ?PaymentResult $settled, ?Order $order, \Throwable $exception): never
    {
        $this->logger->error('Express order could not be placed.', ['exception' => $exception, 'settled' => $settled?->getExternalId()]);
        if ($order !== null) {
            $this->cancel($order, $settled === null && !$exception instanceof ExpressOrderExceptionInterface);
        }
        if ($settlement !== null && $settled !== null) {
            $this->refund($settlement, $settled);
        }
        if ($exception instanceof ExpressOrderExceptionInterface) {
            throw $exception;
        }

        throw new ExpressOrderFailedException('The express order could not be placed: ' . $exception->getMessage(), 1791724086, $exception);
    }

    /**
     * Cancels the committed order, which returns its stock and promotions. When the settlement ended in an
     * unexpected failure (a timeout, say) the gateway may still have taken the money, so the merchant is told
     * which order to reconcile.
     */
    private function cancel(Order $order, bool $outcomeUnknown): void
    {
        if ($outcomeUnknown) {
            $this->logger->critical(sprintf('The express payment for order "%s" ended in an unknown state; check the gateway and refund it if it was charged.', $order->getOrderNumber()));
        }
        $this->orderFinalizationService->reject($order, 'The express payment could not be completed.');
    }

    private function refund(ExpressSettlementInterface $settlement, PaymentResult $settled): void
    {
        try {
            $settlement->refund($settled);
        } catch (\Throwable $refundFailure) {
            $this->logger->critical(
                sprintf('Express payment "%s" was settled but its order failed and the refund failed too; refund it manually.', $settled->getExternalId()),
                ['exception' => $refundFailure]
            );
        }
    }
}
