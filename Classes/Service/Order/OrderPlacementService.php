<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Dto\Checkout\OrderPlacementResult;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Event\BeforeOrderPlacedEvent;
use GoldeneZeiten\Products\Event\PaymentInitiatedEvent;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\Exception\EmptyBasketException;
use GoldeneZeiten\Products\Service\Order\Exception\OrderPlacementVetoedException;
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
        private readonly CreditPointsService $creditPointsService,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function place(
        ServerRequestInterface $request,
        Address $address,
        string $paymentMethodIdentifier,
        CheckoutChoices $choices = new CheckoutChoices()
    ): OrderPlacementResult {
        $basketViewModel = $this->basketService->getBasketViewModel($request);
        $this->assertBasketNotEmpty($basketViewModel);
        if ($choices->getSpendPoints() > 0) {
            $this->creditPointsService->assertSpendable($this->frontendUserResolver->getUid($request), $choices->getSpendPoints());
        }
        $checkoutSelections = new CheckoutSelections(
            $this->basketService->getAppliedVoucherCodes($request),
            $choices->getSpendPoints(),
            $choices->getShippingMethodUid(),
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
