<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Discount;

use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Model\VoucherRedemption;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Event\VoucherRedeemedEvent;
use GoldeneZeiten\Products\Service\Order\Exception\VoucherRedemptionFailedException;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherExceptionInterface;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * The voucher feature, seen through the discount contract. It ships with the extension so a shop has
 * vouchers out of the box, but the checkout reaches it only as a discount provider, which is what lets
 * it move into its own extension later without the core changing.
 *
 * A free-shipping voucher is expressed here, not in shipping: it offsets the carrier's cost by negating
 * the adjustment shipping already produced, so shipping never has to know vouchers exist.
 */
final class VoucherDiscountProvider implements DiscountProviderInterface
{
    public function __construct(
        private readonly VoucherService $voucherService,
        private readonly VoucherRedemptionRepository $voucherRedemptionRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function getIdentifier(): string
    {
        return CoreAdjustmentProvider::VOUCHER;
    }

    public function getPriority(): int
    {
        return 0;
    }

    /**
     * @return CheckoutAdjustment[]
     */
    public function quote(DiscountContext $context): array
    {
        $summary = $this->resolve($context);
        $vouchers = $summary->getAppliedVouchers();
        if ($vouchers === []) {
            return [];
        }

        $adjustments = [
            new CheckoutAdjustment(
                AdjustmentType::DISCOUNT,
                CoreAdjustmentProvider::VOUCHER,
                '',
                $this->negate($summary->getDiscountTotal()),
                0.0,
                ['codes' => implode(',', $this->codes($vouchers))]
            ),
        ];

        $freeShippingOffset = $this->freeShippingOffset($vouchers, $context);
        if ($freeShippingOffset->getCents() !== 0) {
            $adjustments[] = new CheckoutAdjustment(
                AdjustmentType::DISCOUNT,
                CoreAdjustmentProvider::VOUCHER_FREE_SHIPPING,
                '',
                $freeShippingOffset
            );
        }

        return $adjustments;
    }

    public function apply(Order $order, DiscountContext $context): void
    {
        $vouchers = $this->resolve($context)->getAppliedVouchers();
        if ($vouchers === []) {
            return;
        }
        foreach ($vouchers as $voucher) {
            $this->redeemAtomically($voucher);
            $this->voucherRedemptionRepository->add($this->buildRedemption($voucher, $order, $context));
        }
        $this->persistenceManager->persistAll();
        foreach ($vouchers as $voucher) {
            $this->eventDispatcher->dispatch(new VoucherRedeemedEvent($voucher, $order, $voucher->calculateDiscount($context->getGoodsTotal())));
        }
    }

    private function resolve(DiscountContext $context): BasketDiscountSummary
    {
        try {
            return $this->voucherService->resolveAllOrFail($context->getAppliedCodes(), $context->getGoodsTotal(), $context->getFrontendUserUid());
        } catch (VoucherExceptionInterface $exception) {
            throw new VoucherRedemptionFailedException($exception->getMessage(), 1783426407, $exception);
        }
    }

    /**
     * A free-shipping voucher negates the carrier's cost - the adjustment shipping produced - but never
     * the shop's bulky surcharge, which is a separate adjustment it does not touch.
     *
     * @param Voucher[] $vouchers
     */
    private function freeShippingOffset(array $vouchers, DiscountContext $context): Money
    {
        if (!$this->anyWaivesShipping($vouchers)) {
            return Money::fromCents(0);
        }
        $carrierCost = Money::fromCents(0);
        foreach ($context->getAccumulatedAdjustments()->byType(AdjustmentType::SHIPPING) as $adjustment) {
            if ($adjustment->getProviderIdentifier() === CoreAdjustmentProvider::SHIPPING) {
                $carrierCost = $carrierCost->add($adjustment->getAmount());
            }
        }

        return $this->negate($carrierCost);
    }

    /**
     * @param Voucher[] $vouchers
     */
    private function anyWaivesShipping(array $vouchers): bool
    {
        foreach ($vouchers as $voucher) {
            if ($voucher->isWaivingShippingCost()) {
                return true;
            }
        }

        return false;
    }

    private function redeemAtomically(Voucher $voucher): void
    {
        try {
            $this->voucherService->redeemAtomically($voucher);
        } catch (VoucherExceptionInterface $exception) {
            throw new VoucherRedemptionFailedException($exception->getMessage(), 1783426501, $exception);
        }
    }

    private function buildRedemption(Voucher $voucher, Order $order, DiscountContext $context): VoucherRedemption
    {
        $redemption = new VoucherRedemption();
        $redemption->setVoucherUid($voucher->getUid() ?? 0);
        $redemption->setVoucherCode($voucher->getCode());
        $redemption->setOrderUid($order->getUid() ?? 0);
        $redemption->setFrontendUser($context->getFrontendUserUid());
        $redemption->setDiscountTotal($voucher->calculateDiscount($context->getGoodsTotal())->getCents());
        $redemption->setRedeemedAt(new \DateTime());

        return $redemption;
    }

    /**
     * @param Voucher[] $vouchers
     * @return string[]
     */
    private function codes(array $vouchers): array
    {
        return array_map(static fn(Voucher $voucher): string => $voucher->getCode(), $vouchers);
    }

    private function negate(Money $amount): Money
    {
        return Money::fromCents(-$amount->getCents());
    }
}
