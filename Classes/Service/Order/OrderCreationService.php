<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Configuration\CreditPointsConfiguration;
use GoldeneZeiten\Products\Configuration\CreditPointsConfigurationFactory;
use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementContext;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelection;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelectionCriteria;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsRedemption;
use GoldeneZeiten\Products\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Domain\Enum\CreditPointsTransactionType;
use GoldeneZeiten\Products\Domain\Model\CreditPointsTransaction;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Model\VoucherRedemption;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Event\LowStockThresholdReachedEvent;
use GoldeneZeiten\Products\Event\VoucherRedeemedEvent;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsBalanceService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\Exception\VoucherRedemptionFailedException;
use GoldeneZeiten\Products\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherExceptionInterface;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderCreationService
{
    private const DEFAULT_LOW_STOCK_THRESHOLD = 5;
    private const ORDER_TABLE = 'tx_products_domain_model_order';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly StockService $stockService,
        private readonly OrderRepository $orderRepository,
        private readonly OrderFactory $orderFactory,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly VoucherService $voucherService,
        private readonly VoucherRedemptionRepository $voucherRedemptionRepository,
        private readonly CreditPointsService $creditPointsService,
        private readonly CreditPointsBalanceService $creditPointsBalanceService,
        private readonly CreditPointsTransactionRepository $creditPointsTransactionRepository,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ShippingCostService $shippingCostService,
        private readonly HandlingFeeService $handlingFeeService,
        private readonly ProductsConfigurationFactory $configurationFactory,
        private readonly CreditPointsConfigurationFactory $creditPointsConfigurationFactory,
        private readonly TermsSnapshotService $termsSnapshotService
    ) {}

    /**
     * Order placement is atomic: stock decrements, voucher redemptions and credit-point bookings either
     * all land with the order or none of them do. Without the transaction a failure between them would
     * burn a voucher for an order that never came into existence.
     */
    public function create(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): Order {
        $connection = $this->connectionPool->getConnectionForTable(self::ORDER_TABLE);
        $connection->beginTransaction();
        try {
            $order = $this->persistOrder($request, $basketViewModel, $checkoutSelections, $address, $paymentMethod);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->eventDispatcher->dispatch(new AfterOrderPlacedEvent($order, $request));

        return $order;
    }

    private function persistOrder(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): Order {
        $context = $this->resolvePlacementContext($request, $basketViewModel, $checkoutSelections, $address);
        $this->decrementStock($basketViewModel, $request);

        $order = $this->orderFactory->create($request, $basketViewModel, $address, $paymentMethod->getIdentifier(), $context->getDetails());
        $this->orderRepository->add($order);
        $this->persistenceManager->persistAll();

        $this->termsSnapshotService->snapshot($order);
        $this->persistenceManager->persistAll();

        $this->redeemVouchers($context->getVoucherSummary(), $order, $context->getFrontendUser(), $basketViewModel->getTotalGross());
        $this->recordCreditPoints($basketViewModel, $context->getPointsRedemption(), $order, $context->getFrontendUser(), $context->getCreditPointsConfiguration());

        return $order;
    }

    private function resolvePlacementContext(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        CheckoutSelections $checkoutSelections,
        Address $address
    ): PlacementContext {
        $configuration = $this->configurationFactory->create($request);
        $creditPointsConfiguration = $this->creditPointsConfigurationFactory->create($request);
        $frontendUser = $this->frontendUserResolver->getUid($request);
        $voucherSummary = $this->resolveVoucherDiscount($checkoutSelections->getVoucherCodes(), $basketViewModel, $frontendUser);
        $pointsRedemption = $this->resolvePointsRedemption($checkoutSelections->getSpendPoints(), $basketViewModel, $voucherSummary, $frontendUser, $creditPointsConfiguration);
        $shippingCriteria = new ShippingSelectionCriteria(
            $checkoutSelections->getShippingMethodUid(),
            $basketViewModel,
            $address->getCountry(),
            $this->anyVoucherWaivesShipping($voucherSummary->getAppliedVouchers())
        );
        $shippingSelection = $this->shippingCostService->resolveSelection($configuration, $shippingCriteria, $request);
        $handlingFeeCost = $this->handlingFeeService->resolveCost($configuration, $basketViewModel, $address->getCountry(), $request);

        $details = new PlacementDetails(
            $this->buildAdjustments($basketViewModel, $voucherSummary, $pointsRedemption, $shippingSelection, $handlingFeeCost),
            $this->voucherCodes($voucherSummary),
            $shippingSelection->getShippingMethodUid(),
            $checkoutSelections->getDeliveryAddress(),
            $checkoutSelections->getGiftMessage()
        );

        return new PlacementContext($details, $voucherSummary, $pointsRedemption, $creditPointsConfiguration, $frontendUser);
    }

    /**
     * The order's money effects, in the order they apply. Each is a signed amount, so a later feature can
     * offset an earlier one instead of having to reach into it.
     */
    private function buildAdjustments(
        BasketViewModel $basketViewModel,
        BasketDiscountSummary $voucherSummary,
        CreditPointsRedemption $pointsRedemption,
        ShippingSelection $shippingSelection,
        Money $handlingFeeCost
    ): AdjustmentCollection {
        $candidates = [
            new CheckoutAdjustment(
                AdjustmentType::SHIPPING,
                'core.shipping',
                $shippingSelection->getShippingMethod()?->getTitle() ?? '',
                $shippingSelection->getCost(),
                $shippingSelection->getTaxRate()
            ),
            new CheckoutAdjustment(AdjustmentType::HANDLING, 'core.handling', '', $handlingFeeCost),
            new CheckoutAdjustment(AdjustmentType::DISCOUNT, 'core.voucher', '', $this->negate($voucherSummary->getDiscountTotal())),
            new CheckoutAdjustment(AdjustmentType::LOYALTY, 'core.credit_points', '', $this->negate($pointsRedemption->getDiscountAmount())),
            new CheckoutAdjustment(AdjustmentType::DEPOSIT, 'core.deposit', '', $basketViewModel->getDepositTotal()),
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

    /**
     * @return string[]
     */
    private function voucherCodes(BasketDiscountSummary $voucherSummary): array
    {
        return array_map(
            static fn(Voucher $voucher): string => $voucher->getCode(),
            $voucherSummary->getAppliedVouchers()
        );
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

    /**
     * @param string[] $voucherCodes
     */
    private function resolveVoucherDiscount(array $voucherCodes, BasketViewModel $basketViewModel, int $frontendUser): BasketDiscountSummary
    {
        try {
            return $this->voucherService->resolveAllOrFail($voucherCodes, $basketViewModel->getTotalGross(), $frontendUser);
        } catch (VoucherExceptionInterface $exception) {
            throw new VoucherRedemptionFailedException($exception->getMessage(), 1783426407, $exception);
        }
    }

    /**
     * @param Voucher[] $vouchers
     */
    private function anyVoucherWaivesShipping(array $vouchers): bool
    {
        foreach ($vouchers as $voucher) {
            if ($voucher->isWaivingShippingCost()) {
                return true;
            }
        }
        return false;
    }

    private function resolvePointsRedemption(
        int $spendPoints,
        BasketViewModel $basketViewModel,
        BasketDiscountSummary $voucherSummary,
        int $frontendUser,
        CreditPointsConfiguration $creditPointsConfiguration
    ): CreditPointsRedemption {
        $remainingGoodsTotal = $basketViewModel->getTotalGross()->subtract($voucherSummary->getDiscountTotal());
        return $this->creditPointsService->redeem($frontendUser, $spendPoints, $remainingGoodsTotal, $creditPointsConfiguration);
    }

    private function redeemVouchers(BasketDiscountSummary $discountSummary, Order $order, int $frontendUser, Money $basketGoodsTotal): void
    {
        $vouchers = $discountSummary->getAppliedVouchers();
        if ($vouchers === []) {
            return;
        }
        foreach ($vouchers as $voucher) {
            $this->redeemVoucherAtomically($voucher);
            $this->voucherRedemptionRepository->add($this->buildRedemption($voucher, $order, $frontendUser, $basketGoodsTotal));
        }
        $this->persistenceManager->persistAll();
        foreach ($vouchers as $voucher) {
            $this->eventDispatcher->dispatch(new VoucherRedeemedEvent($voucher, $order, $voucher->calculateDiscount($basketGoodsTotal)));
        }
    }

    /**
     * Atomic guard prevents concurrent redemption bypass. {@see StockService::decrementForItem()}
     */
    private function redeemVoucherAtomically(Voucher $voucher): void
    {
        try {
            $this->voucherService->redeemAtomically($voucher);
        } catch (VoucherExceptionInterface $exception) {
            throw new VoucherRedemptionFailedException($exception->getMessage(), 1783426501, $exception);
        }
    }

    private function buildRedemption(Voucher $voucher, Order $order, int $frontendUser, Money $basketGoodsTotal): VoucherRedemption
    {
        $redemption = new VoucherRedemption();
        $redemption->setVoucherUid($voucher->getUid() ?? 0);
        $redemption->setVoucherCode($voucher->getCode());
        $redemption->setOrderUid($order->getUid() ?? 0);
        $redemption->setFrontendUser($frontendUser);
        $redemption->setDiscountTotal($voucher->calculateDiscount($basketGoodsTotal)->getCents());
        $redemption->setRedeemedAt(new \DateTime());
        return $redemption;
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
