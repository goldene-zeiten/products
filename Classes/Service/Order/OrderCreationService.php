<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Model\VoucherRedemption;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Event\VoucherRedeemedEvent;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
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
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    /**
     * @param string[] $voucherCodes
     */
    public function create(
        ServerRequestInterface $request,
        BasketViewModel $basketViewModel,
        array $voucherCodes,
        Address $address,
        PaymentMethodInterface $paymentMethod
    ): Order {
        $frontendUser = $this->frontendUserResolver->getUid($request);
        $discountSummary = $this->resolveDiscount($voucherCodes, $basketViewModel, $frontendUser);

        $this->decrementStock($basketViewModel);

        $order = $this->orderFactory->create($request, $basketViewModel, $address, $paymentMethod->getIdentifier(), $discountSummary);
        $this->orderRepository->add($order);
        $this->persistenceManager->persistAll();

        $this->redeemVouchers($discountSummary, $order, $frontendUser, $basketViewModel->getTotalGross());
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
    private function resolveDiscount(array $voucherCodes, BasketViewModel $basketViewModel, int $frontendUser): BasketDiscountSummary
    {
        try {
            return $this->voucherService->resolveAllOrFail($voucherCodes, $basketViewModel->getTotalGross(), $frontendUser);
        } catch (VoucherExceptionInterface $exception) {
            throw new VoucherRedemptionFailedException($exception->getMessage(), 1783426407, $exception);
        }
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
}
