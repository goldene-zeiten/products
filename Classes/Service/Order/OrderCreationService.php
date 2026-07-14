<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Configuration\CreditPointsConfiguration;
use GoldeneZeiten\Products\Configuration\CreditPointsConfigurationFactory;
use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Discount\DiscountContextFactory;
use GoldeneZeiten\Products\Discount\DiscountRegistry;
use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementContext;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsRedemption;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingSelection;
use GoldeneZeiten\Products\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Domain\Enum\CreditPointsTransactionType;
use GoldeneZeiten\Products\Domain\Model\CreditPointsTransaction;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Event\LowStockThresholdReachedEvent;
use GoldeneZeiten\Products\Payment\PaymentContextFactory;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsBalanceService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Shipping\ShippingContextFactory;
use GoldeneZeiten\Products\Shipping\ShippingQuoteService;
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
        private readonly CreditPointsService $creditPointsService,
        private readonly CreditPointsBalanceService $creditPointsBalanceService,
        private readonly CreditPointsTransactionRepository $creditPointsTransactionRepository,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ShippingQuoteService $shippingQuoteService,
        private readonly ShippingContextFactory $shippingContextFactory,
        private readonly HandlingFeeService $handlingFeeService,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly CreditPointsConfigurationFactory $creditPointsConfigurationFactory,
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
        $this->recordCreditPoints($basketViewModel, $context->getPointsRedemption(), $order, $context->getFrontendUser(), $context->getCreditPointsConfiguration());
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
        $creditPointsConfiguration = $this->creditPointsConfigurationFactory->create($request);
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
        $pointsRedemption = $this->resolvePointsRedemption($checkoutSelections->getSpendPoints(), $basketViewModel, $adjustments->getDiscountTotal(), $frontendUser, $creditPointsConfiguration);
        $adjustments = $this->withLoyalty($adjustments, $pointsRedemption);
        $adjustments = $this->withPaymentFee($adjustments, $paymentMethod, $basketViewModel, $address, $frontendUser);

        $details = new PlacementDetails(
            $adjustments,
            $shippingSelection,
            $checkoutSelections->getDeliveryAddress(),
            $checkoutSelections->getGiftMessage()
        );

        return new PlacementContext($details, $discountContext, $pointsRedemption, $creditPointsConfiguration, $frontendUser);
    }

    private function withLoyalty(AdjustmentCollection $adjustments, CreditPointsRedemption $pointsRedemption): AdjustmentCollection
    {
        if ($pointsRedemption->getDiscountAmount()->getCents() === 0) {
            return $adjustments;
        }

        return $adjustments->with(new CheckoutAdjustment(
            AdjustmentType::LOYALTY,
            CoreAdjustmentProvider::CREDIT_POINTS,
            '',
            $this->negate($pointsRedemption->getDiscountAmount())
        ));
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

    private function negate(Money $amount): Money
    {
        return Money::fromCents(-$amount->getCents());
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

    private function resolvePointsRedemption(
        int $spendPoints,
        BasketViewModel $basketViewModel,
        Money $discountTotal,
        int $frontendUser,
        CreditPointsConfiguration $creditPointsConfiguration
    ): CreditPointsRedemption {
        $remainingGoodsTotal = $basketViewModel->getTotalGross()->subtract($discountTotal);
        return $this->creditPointsService->redeem($frontendUser, $spendPoints, $remainingGoodsTotal, $creditPointsConfiguration);
    }

    private function recordCreditPoints(BasketViewModel $basketViewModel, CreditPointsRedemption $pointsRedemption, Order $order, int $frontendUser, CreditPointsConfiguration $creditPointsConfiguration): void
    {
        if (!$creditPointsConfiguration->isEnabled() || $frontendUser === 0) {
            return;
        }
        $earned = $this->creditPointsService->calculateEarnedPoints($basketViewModel, $creditPointsConfiguration);
        if ($pointsRedemption->getPoints() > 0) {
            $this->redeemCreditPointsAtomically($frontendUser, $pointsRedemption->getPoints());
            $this->creditPointsTransactionRepository->add($this->buildTransaction($frontendUser, $order, -$pointsRedemption->getPoints(), CreditPointsTransactionType::REDEEM));
        }
        if ($earned > 0) {
            $this->creditPointsBalanceService->credit($frontendUser, $earned);
            $this->creditPointsTransactionRepository->add($this->buildTransaction($frontendUser, $order, $earned, CreditPointsTransactionType::EARN));
        }
        if ($earned > 0 || $pointsRedemption->getPoints() > 0) {
            $this->persistenceManager->persistAll();
        }
    }

    /**
     * Atomic guard prevents concurrent redemption bypass. {@see StockService::decrementForItem()}
     */
    private function redeemCreditPointsAtomically(int $frontendUser, int $points): void
    {
        if (!$this->creditPointsBalanceService->debitIfAffordable($frontendUser, $points)) {
            throw new InsufficientCreditPointsException(
                sprintf('Requested %d credit points but the balance could not afford it at redemption time.', $points),
                1783430100
            );
        }
    }

    private function buildTransaction(int $frontendUser, Order $order, int $points, CreditPointsTransactionType $type): CreditPointsTransaction
    {
        $transaction = new CreditPointsTransaction();
        $transaction->setFrontendUser($frontendUser);
        $transaction->setOrderUid($order->getUid() ?? 0);
        $transaction->setPoints($points);
        $transaction->setType($type);
        $transaction->setCreated(new \DateTime());
        return $transaction;
    }
}
