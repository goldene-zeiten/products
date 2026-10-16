<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
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
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderTotals;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingSelection;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Domain\ValueObject\VatBuckets;
use GoldeneZeiten\Products\Core\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Core\Event\LowStockThresholdReachedEvent;
use GoldeneZeiten\Products\Core\Loyalty\LoyaltyRegistry;
use GoldeneZeiten\Products\Core\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Pricing\VatSplitter;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Core\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Core\Service\TaxService;
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
        private readonly TermsSnapshotService $termsSnapshotService,
        private readonly TaxService $taxService,
        private readonly OrderTotalsCalculator $orderTotalsCalculator,
        private readonly VatSplitter $vatSplitter
    ) {}

    /**
     * Stock decrements, voucher redemptions and credit-point bookings must not survive a failed
     * placement. They are made atomic by the transaction {@see OrderPlacementTransaction::run()} opens
     * around this service - this service deliberately opens none of its own. A payment that cannot be
     * started afterwards cancels the committed order instead.
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
        $this->loyaltyRegistry->award($order, $context->getLoyaltyContext()->withRemainingGoodsTotal(
            $basketViewModel->getTotalGross()->subtract($context->getDetails()->getAdjustments()->getGoodsReductionTotal())
        ));
        $this->eventDispatcher->dispatch(new AfterOrderPlacedEvent($order, $request));

        return $order;
    }

    /**
     * What placing the order would charge, line by line - computed exactly as {@see create()} computes it,
     * without booking anything. The checkout review shows this, so it shows the amount that is charged.
     */
    public function quote(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address,
        ?PaymentMethodInterface $paymentMethod
    ): OrderTotals {
        $details = $this->resolvePlacementContext($request, $basketViewModel, $checkoutSelections, $address, $paymentMethod)->getDetails();
        return $this->orderTotalsCalculator->calculate(
            $basketViewModel,
            $details->getAdjustments(),
            $this->configurationFactory->create($request)->getRoundingMode()
        );
    }

    private function resolvePlacementContext(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address,
        ?PaymentMethodInterface $paymentMethod
    ): PlacementContext {
        $configuration = $this->configurationFactory->create($request);
        $frontendUser = $this->frontendUserResolver->getUid($request);
        $shippingContext = $this->shippingContextFactory->createFromBasket($basketViewModel, $address, $frontendUser);
        $shippingSelection = $this->shippingQuoteService->resolveSelection($configuration, $shippingContext, $checkoutSelections->getShippingOptionKey(), $request);
        $handlingFeeCost = $this->handlingFeeService->resolveCost($configuration, $basketViewModel, $address->getCountry(), $request);

        // Charges first, then discounts that may offset them, then loyalty against what remains, then the
        // payment fee on the final total.
        $ancillaryTaxRate = fn(): float => $this->ancillaryTaxRate($configuration, $shippingSelection, $address);
        $adjustments = $this->baseAdjustments($basketViewModel, $shippingSelection, $handlingFeeCost, $ancillaryTaxRate);
        $discountContext = $this->discountContextFactory->createFromBasket($basketViewModel, $frontendUser, $request, $adjustments);
        foreach ($this->discountRegistry->collect($discountContext) as $discountAdjustment) {
            $adjustments = $adjustments->with($discountAdjustment);
        }
        $loyaltyContext = new LoyaltyContext(
            $request,
            $basketViewModel,
            $basketViewModel->getTotalGross()->subtract($adjustments->getGoodsReductionTotal()),
            $frontendUser
        );
        foreach ($this->loyaltyRegistry->collectRedemption($loyaltyContext) as $loyaltyAdjustment) {
            $adjustments = $adjustments->with($loyaltyAdjustment);
        }
        if ($paymentMethod !== null) {
            $paymentContext = $this->paymentContextFactory->createFromBasket($basketViewModel, $address, $frontendUser, $shippingSelection->getKey());
            $adjustments = $this->withPaymentFee($adjustments, $paymentMethod, $paymentContext, $basketViewModel, $ancillaryTaxRate);
        }

        $details = new PlacementDetails(
            $adjustments,
            $shippingSelection,
            $checkoutSelections->getDeliveryAddress(),
            $checkoutSelections->getGiftMessage(),
            $checkoutSelections->isWithdrawalWaiverAccepted(),
            $checkoutSelections->getCustomerNote(),
            $checkoutSelections->getDesiredDeliveryDate(),
        );

        return new PlacementContext($details, $discountContext, $loyaltyContext, $frontendUser);
    }

    /**
     * A payment method may charge a surcharge for paying that way, or grant a discount. It is the last
     * adjustment, so it applies to the total the customer actually owes after discounts. A surcharge is
     * ancillary to the delivery and carries its VAT; a discount lowers the price of the goods, so it is
     * split across their rates like any other discount.
     *
     * @param \Closure(): float $ancillaryTaxRate
     */
    private function withPaymentFee(
        AdjustmentCollection $adjustments,
        PaymentMethodInterface $paymentMethod,
        PaymentContext $paymentContext,
        BasketViewModel $basketViewModel,
        \Closure $ancillaryTaxRate
    ): AdjustmentCollection {
        $fee = $paymentMethod->calculateFee($paymentContext);
        if ($fee === 0) {
            return $adjustments;
        }
        $adjustment = new CheckoutAdjustment(
            $fee < 0 ? AdjustmentType::DISCOUNT : AdjustmentType::PAYMENT_FEE,
            'core.payment.' . $paymentMethod->getIdentifier(),
            $paymentMethod->getLabel(),
            Money::fromCents($fee)
        );
        $parts = $fee < 0
            ? $this->vatSplitter->splitAdjustment($adjustment, VatBuckets::fromBasket($basketViewModel))
            : [$adjustment->withTaxRate($ancillaryTaxRate())];
        foreach ($parts as $part) {
            $adjustments = $adjustments->with($part);
        }
        return $adjustments;
    }

    /**
     * Handling and payment fees are ancillary to the delivery, so they carry the VAT the shipping does -
     * the chosen option's rate, or the shop's shipping rate when nothing ships.
     */
    private function ancillaryTaxRate(ProductsConfiguration $configuration, ShippingSelection $shippingSelection, Address $address): float
    {
        if ($shippingSelection->getOption() !== null) {
            return $shippingSelection->getTaxRate();
        }
        return $this->taxService->getShippingTaxRate($configuration, null, $address->getCountry() !== '' ? $address->getCountry() : null);
    }

    /**
     * The charges the discounts then run against: what the carrier bills, what the shop adds on top, the
     * handling fee and the deposit. Discounts and the payment fee are layered on afterwards.
     *
     * The deposit is part of the price of the goods it is charged on, so it carries their VAT rate.
     *
     * @param \Closure(): float $ancillaryTaxRate
     */
    private function baseAdjustments(
        BasketViewModel $basketViewModel,
        ShippingSelection $shippingSelection,
        Money $handlingFeeCost,
        \Closure $ancillaryTaxRate
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
            new CheckoutAdjustment(
                AdjustmentType::HANDLING,
                CoreAdjustmentProvider::HANDLING,
                '',
                $handlingFeeCost,
                $handlingFeeCost->getCents() !== 0 ? $ancillaryTaxRate() : 0.0
            ),
        ];
        foreach ($this->depositByTaxRate($basketViewModel)->all() as $bucket) {
            $candidates[] = new CheckoutAdjustment(AdjustmentType::DEPOSIT, CoreAdjustmentProvider::DEPOSIT, '', $bucket['gross'], $bucket['rate']);
        }

        return new AdjustmentCollection(...array_filter(
            $candidates,
            static fn(CheckoutAdjustment $adjustment): bool => $adjustment->getAmount()->getCents() !== 0
        ));
    }

    private function depositByTaxRate(BasketViewModel $basketViewModel): VatBuckets
    {
        $deposits = VatBuckets::empty();
        foreach ($basketViewModel->getItems() as $item) {
            $deposits = $deposits->with($item->getTaxRate(), $item->getDepositTotal());
        }
        return $deposits;
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
