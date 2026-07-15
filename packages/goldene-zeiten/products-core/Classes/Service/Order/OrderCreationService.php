<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Discount\DiscountContextFactory;
use GoldeneZeiten\Products\Core\Discount\DiscountRegistry;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\PlacementContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Core\Domain\Dto\Loyalty\LoyaltyContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingSelection;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Core\Event\LowStockThresholdReachedEvent;
use GoldeneZeiten\Products\Core\Loyalty\LoyaltyRegistry;
use GoldeneZeiten\Products\Core\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Core\Shipping\ShippingContextFactory;
use GoldeneZeiten\Products\Core\Shipping\ShippingQuoteService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderCreationService
{
    private const DEFAULT_LOW_STOCK_THRESHOLD = 5;

    public function __construct(
        private readonly PaymentContextFactory $paymentContextFactory,
        private readonly StockService $stockService,
        private readonly OrderRepository $orderRepository,
        private readonly OrderFactory $orderFactory,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DiscountRegistry $discountRegistry,
        private readonly DiscountContextFactory $discountContextFactory,
        private readonly LoyaltyRegistry $loyaltyRegistry,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ShippingQuoteService $shippingQuoteService,
        private readonly ShippingContextFactory $shippingContextFactory,
        private readonly HandlingFeeService $handlingFeeService,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly TermsSnapshotService $termsSnapshotService
    ) {}

    /**
     * Stock decrements, voucher redemptions and credit-point bookings must not survive a failed
     * placement. They are made atomic by the transaction {@see OrderPlacementTransaction::run()} opens
     * around this service and the payment initiation that follows it - this service deliberately opens
     * none of its own, so that payment failures roll back the bookings too.
     */
    public function create(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): Order {
        $context = $this->resolvePlacementContext($request, $basketViewModel, $checkoutSelections, $address, $paymentMethod);
        $this->decrementStock($basketViewModel, $request);

        $order = $this->orderFactory->create($request, $basketViewModel, $address, $paymentMethod->getIdentifier(), $context->getDetails());
        $this->orderRepository->add($order);
        $this->persistenceManager->persistAll();

        $this->termsSnapshotService->snapshot($order);
        $this->persistenceManager->persistAll();

        $this->discountRegistry->apply($order, $context->getDiscountContext());
        $this->loyaltyRegistry->applyRedemption($order, $context->getLoyaltyContext());
        $this->loyaltyRegistry->award($order, $context->getLoyaltyContext());
        $this->eventDispatcher->dispatch(new AfterOrderPlacedEvent($order, $request));

        return $order;
    }

    private function resolvePlacementContext(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): PlacementContext {
        $configuration = $this->configurationFactory->create($request);
        $frontendUser = $this->frontendUserResolver->getUid($request);
        $shippingContext = $this->shippingContextFactory->createFromBasket($basketViewModel, $address, $frontendUser);
        $shippingSelection = $this->shippingQuoteService->resolveSelection($configuration, $shippingContext, $checkoutSelections->getShippingOptionKey(), $request);
        $handlingFeeCost = $this->handlingFeeService->resolveCost($configuration, $basketViewModel, $address->getCountry(), $request);

        // Charges first, then discounts that may offset them, then loyalty against what remains, then the
        // payment fee on the final total.
        $adjustments = $this->baseAdjustments($basketViewModel, $shippingSelection, $handlingFeeCost);
        $discountContext = $this->discountContextFactory->createFromBasket($basketViewModel, $frontendUser, $checkoutSelections->getVoucherCodes(), $adjustments);
        foreach ($this->discountRegistry->collect($discountContext) as $discountAdjustment) {
            $adjustments = $adjustments->with($discountAdjustment);
        }
        $loyaltyContext = new LoyaltyContext(
            $request,
            $basketViewModel,
            $basketViewModel->getTotalGross()->subtract($adjustments->getDiscountTotal()),
            $frontendUser
        );
        foreach ($this->loyaltyRegistry->collectRedemption($loyaltyContext) as $loyaltyAdjustment) {
            $adjustments = $adjustments->with($loyaltyAdjustment);
        }
        $adjustments = $this->withPaymentFee($adjustments, $paymentMethod, $basketViewModel, $address, $frontendUser);

        $details = new PlacementDetails(
            $adjustments,
            $shippingSelection,
            $checkoutSelections->getDeliveryAddress(),
            $checkoutSelections->getGiftMessage()
        );

        return new PlacementContext($details, $discountContext, $loyaltyContext, $frontendUser);
    }

    /**
     * A payment method may charge a surcharge for paying that way. It is the last adjustment, so the fee
     * applies to the total the customer actually owes after discounts.
     */
    private function withPaymentFee(
        AdjustmentCollection $adjustments,
        PaymentMethodInterface $paymentMethod,
        BasketViewModel $basketViewModel,
        Address $address,
        int $frontendUser
    ): AdjustmentCollection {
        $paymentContext = $this->paymentContextFactory->createFromBasket($basketViewModel, $address, $frontendUser);
        $fee = $paymentMethod->calculateFee($paymentContext);
        if ($fee === 0) {
            return $adjustments;
        }

        return $adjustments->with(new CheckoutAdjustment(
            AdjustmentType::PAYMENT_FEE,
            'core.payment.' . $paymentMethod->getIdentifier(),
            $paymentMethod->getLabel(),
            Money::fromCents($fee)
        ));
    }

    /**
     * The charges the discounts then run against: what the carrier bills, what the shop adds on top, the
     * handling fee and the deposit. Discounts and the payment fee are layered on afterwards.
     */
    private function baseAdjustments(
        BasketViewModel $basketViewModel,
        ShippingSelection $shippingSelection,
        Money $handlingFeeCost
    ): AdjustmentCollection {
        $candidates = [
            new CheckoutAdjustment(
                AdjustmentType::SHIPPING,
                CoreAdjustmentProvider::SHIPPING,
                $shippingSelection->getLabel(),
                $shippingSelection->getCarrierCost(),
                $shippingSelection->getTaxRate()
            ),
            // Kept apart from the carrier's own rate: a free-shipping voucher waives what the carrier
            // charges, not what an oversized item costs this shop to handle.
            new CheckoutAdjustment(
                AdjustmentType::SHIPPING,
                CoreAdjustmentProvider::SHIPPING_SURCHARGE,
                '',
                $shippingSelection->getSurcharge(),
                $shippingSelection->getTaxRate()
            ),
            new CheckoutAdjustment(AdjustmentType::HANDLING, CoreAdjustmentProvider::HANDLING, '', $handlingFeeCost),
            new CheckoutAdjustment(AdjustmentType::DEPOSIT, CoreAdjustmentProvider::DEPOSIT, '', $basketViewModel->getDepositTotal()),
        ];

        return new AdjustmentCollection(...array_filter(
            $candidates,
            static fn(CheckoutAdjustment $adjustment): bool => $adjustment->getAmount()->getCents() !== 0
        ));
    }

    private function decrementStock(BasketViewModel $basketViewModel, ServerRequestInterface $request): void
    {
        $threshold = $this->lowStockThreshold($request);
        foreach ($basketViewModel->getItems() as $viewItem) {
            if ($this->hasUnlimitedStock($viewItem)) {
                continue;
            }
            $newStock = $this->stockService->decrementForItem(
                $viewItem->getProduct()->getUid() ?? 0,
                $viewItem->getArticle()?->getUid(),
                $viewItem->getQuantity()
            );
            if ($newStock <= $threshold) {
                $this->dispatchLowStockEvent($viewItem, $newStock);
            }
        }
    }

    /**
     * Either product or article unlimited exempts the line from stock tracking.
     */
    private function hasUnlimitedStock(BasketViewItem $viewItem): bool
    {
        return $viewItem->getProduct()->isUnlimitedStock() || ($viewItem->getArticle()?->isUnlimitedStock() ?? false);
    }

    private function dispatchLowStockEvent(BasketViewItem $viewItem, int $newStock): void
    {
        $article = $viewItem->getArticle();
        $this->eventDispatcher->dispatch(new LowStockThresholdReachedEvent(
            $viewItem->getProduct()->getUid() ?? 0,
            $article?->getUid(),
            $article?->getTitle() ?? $viewItem->getProduct()->getTitle(),
            $newStock
        ));
    }

    private function lowStockThreshold(ServerRequestInterface $request): int
    {
        $threshold = $request->getAttribute('site')?->getSettings()->get('products.stock.lowStockThreshold', self::DEFAULT_LOW_STOCK_THRESHOLD);
        return (int)($threshold ?? self::DEFAULT_LOW_STOCK_THRESHOLD);
    }

}
