<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\DiscountRequest;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementDiscount;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsRedemption;
use GoldeneZeiten\Products\Domain\Enum\CreditPointsTransactionType;
use GoldeneZeiten\Products\Domain\Model\CreditPointsTransaction;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Model\VoucherRedemption;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Event\VoucherRedeemedEvent;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\Exception\VoucherRedemptionFailedException;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherExceptionInterface;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderCreationService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly OrderRepository $orderRepository,
        private readonly OrderFactory $orderFactory,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly VoucherService $voucherService,
        private readonly VoucherRedemptionRepository $voucherRedemptionRepository,
        private readonly CreditPointsService $creditPointsService,
        private readonly CreditPointsTransactionRepository $creditPointsTransactionRepository,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function create(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        DiscountRequest $discountRequest,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): Order {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        $voucherSummary = $this->resolveVoucherDiscount($discountRequest->getVoucherCodes(), $basketViewModel, $frontendUser);
        $pointsRedemption = $this->resolvePointsRedemption($discountRequest->getSpendPoints(), $basketViewModel, $voucherSummary, $frontendUser);
        $discount = new PlacementDiscount($voucherSummary, $pointsRedemption->getDiscountAmount());

        $this->decrementStock($basketViewModel);

        $order = $this->orderFactory->create($request, $basketViewModel, $address, $paymentMethod->getIdentifier(), $discount);
        $this->orderRepository->add($order);
        $this->persistenceManager->persistAll();

        $this->redeemVouchers($voucherSummary, $order, $frontendUser, $basketViewModel->getTotalGross());
        $this->recordCreditPoints($basketViewModel, $pointsRedemption, $order, $frontendUser);
        $this->eventDispatcher->dispatch(new AfterOrderPlacedEvent($order, $request));

        return $order;
    }

    private function decrementStock(BasketViewModel $basketViewModel): void
    {
        foreach ($basketViewModel->getItems() as $viewItem) {
            $this->stockService->decrementForItem(
                $viewItem->getProduct()->getUid() ?? 0,
                $viewItem->getArticle()?->getUid(),
                $viewItem->getQuantity()
            );
        }
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

    private function resolvePointsRedemption(
        int $spendPoints,
        BasketViewModel $basketViewModel,
        BasketDiscountSummary $voucherSummary,
        int $frontendUser
    ): CreditPointsRedemption {
        $remainingGoodsTotal = $basketViewModel->getTotalGross()->subtract($voucherSummary->getDiscountTotal());
        return $this->creditPointsService->redeem($frontendUser, $spendPoints, $remainingGoodsTotal);
    }

    private function redeemVouchers(BasketDiscountSummary $discountSummary, Order $order, int $frontendUser, Money $basketGoodsTotal): void
    {
        $vouchers = $discountSummary->getAppliedVouchers();
        if ($vouchers === []) {
            return;
        }
        foreach ($vouchers as $voucher) {
            $this->voucherRedemptionRepository->add($this->buildRedemption($voucher, $order, $frontendUser, $basketGoodsTotal));
        }
        $this->persistenceManager->persistAll();
        foreach ($vouchers as $voucher) {
            $this->eventDispatcher->dispatch(new VoucherRedeemedEvent($voucher, $order, $voucher->calculateDiscount($basketGoodsTotal)));
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

    /**
     * Guests (frontend_user 0) never touch the ledger, and nothing is written at all while the
     * feature is disabled sitewide - existing installations see no behaviour change until an
     * operator opts in.
     */
    private function recordCreditPoints(BasketViewModel $basketViewModel, CreditPointsRedemption $pointsRedemption, Order $order, int $frontendUser): void
    {
        if (!$this->creditPointsService->isEnabled() || $frontendUser === 0) {
            return;
        }
        $earned = $this->creditPointsService->calculateEarnedPoints($basketViewModel);
        if ($earned > 0) {
            $this->creditPointsTransactionRepository->add($this->buildTransaction($frontendUser, $order, $earned, CreditPointsTransactionType::EARN));
        }
        if ($pointsRedemption->getPoints() > 0) {
            $this->creditPointsTransactionRepository->add($this->buildTransaction($frontendUser, $order, -$pointsRedemption->getPoints(), CreditPointsTransactionType::REDEEM));
        }
        if ($earned > 0 || $pointsRedemption->getPoints() > 0) {
            $this->persistenceManager->persistAll();
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
