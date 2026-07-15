<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Discount;

use GoldeneZeiten\Products\Core\Discount\DiscountProviderInterface;
use GoldeneZeiten\Products\Core\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\CoreAdjustmentProvider;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Voucher\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use GoldeneZeiten\Products\Voucher\Domain\Model\VoucherRedemption;
use GoldeneZeiten\Products\Voucher\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Voucher\Event\VoucherRedeemedEvent;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherExceptionInterface;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherRedemptionFailedException;
use GoldeneZeiten\Products\Voucher\Service\VoucherService;
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
    /**
     * The identifiers this feature tags its adjustments with. Owned here, not by the core, so the core
     * stays unaware that a voucher programme exists at all.
     */
    public const IDENTIFIER = 'voucher';
    public const FREE_SHIPPING = 'voucher.free_shipping';

    public function __construct(
        private readonly VoucherService $voucherService,
        private readonly VoucherRedemptionRepository $voucherRedemptionRepository,
        private readonly VoucherCheckoutState $voucherCheckoutState,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
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
                self::IDENTIFIER,
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
                self::FREE_SHIPPING,
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
            return $this->voucherService->resolveAllOrFail($this->voucherCheckoutState->getCodes($context->getRequest()), $context->getGoodsTotal(), $context->getFrontendUserUid());
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
