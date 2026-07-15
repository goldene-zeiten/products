<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\OrderPlacementResult;
use GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Event\BeforeOrderPlacedEvent;
use GoldeneZeiten\Products\Core\Event\PaymentInitiatedEvent;
use GoldeneZeiten\Products\Core\Loyalty\LoyaltyRegistry;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\Order\Exception\EmptyBasketException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementVetoedException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\TermsNotAcceptedException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;

final class OrderPlacementService
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly PaymentMethodRegistry $paymentMethodRegistry,
        private readonly OrderPlacementTransaction $orderPlacementTransaction,
        private readonly OrderFinalizationService $orderFinalizationService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoyaltyRegistry $loyaltyRegistry,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly PriceQuoteService $priceQuoteService,
        private readonly ProductsConfigurationFactory $configurationFactory
    ) {}

    public function place(
        ServerRequestInterface $request,
        Address $address,
        string $paymentMethodIdentifier,
        CheckoutChoices $choices = new CheckoutChoices()
    ): OrderPlacementResult {
        $liveBasketViewModel = $this->basketService->getBasketViewModel($request);
        $configuration = $this->configurationFactory->create($request);
        $basketViewModel = $this->priceQuoteService->resolve($request, $liveBasketViewModel, $configuration);
        $this->assertBasketNotEmpty($basketViewModel);
        if (!$choices->isTermsAccepted()) {
            throw new TermsNotAcceptedException('Please accept the terms and conditions before placing your order.', 1752422400);
        }
        $liveBasket = $this->basketService->getBasketViewModel($request);
        $this->loyaltyRegistry->assertRedeemable(new LoyaltyContext(
            $request,
            $liveBasket,
            $liveBasket->getTotalGross(),
            $this->frontendUserResolver->getUid($request)
        ));
        $checkoutSelections = new CheckoutSelections(
            $choices->getShippingOptionKey(),
            $choices->getDeliveryAddress(),
            $choices->getGiftMessage()
        );
        $paymentMethod = $this->paymentMethodRegistry->get($paymentMethodIdentifier);
        $this->dispatchBeforeOrderPlaced($request, $basketViewModel, $address, $paymentMethod);

        [$order, $paymentResult] = $this->orderPlacementTransaction->run($request, $basketViewModel, $checkoutSelections, $address, $paymentMethod);
        $this->eventDispatcher->dispatch(new PaymentInitiatedEvent($order, $paymentResult));

        return $this->buildPlacementResult($order, $paymentResult, $request);
    }

    private function assertBasketNotEmpty(BasketViewModel $basketViewModel): void
    {
        if ($basketViewModel->isEmpty()) {
            throw new EmptyBasketException('Basket is empty.', 1751751040);
        }
    }

    private function dispatchBeforeOrderPlaced(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): void {
        $event = new BeforeOrderPlacedEvent($request, $basketViewModel, $address, $paymentMethod);
        $this->eventDispatcher->dispatch($event);
        if ($event->isVetoed()) {
            throw new OrderPlacementVetoedException($event->getVetoReason(), 1751751041);
        }
    }

    private function buildPlacementResult(Order $order, PaymentResult $paymentResult, ServerRequestInterface $request): OrderPlacementResult
    {
        if ($paymentResult->getState() === PaymentResultState::REDIRECT_REQUIRED) {
            return OrderPlacementResult::forRedirect($order, $paymentResult->getRedirectUrl());
        }

        $this->orderFinalizationService->finalize($order, $paymentResult, $request);
        return OrderPlacementResult::forOrder($order);
    }
}
