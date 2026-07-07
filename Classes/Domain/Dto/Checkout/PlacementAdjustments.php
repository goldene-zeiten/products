<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Combines the resolved amounts OrderFactory needs to apply to an order's totals: vouchers and
 * points reduce it, shipping adds to it. Vouchers carry their own codes for the order snapshot,
 * points only ever contribute an amount.
 */
#[Exclude]
final readonly class PlacementAdjustments
{
    public function __construct(
        private BasketDiscountSummary $voucherSummary,
        private Money $pointsDiscountAmount,
        private ShippingSelection $shippingSelection
    ) {}

    public function getTotalDiscount(): Money
    {
        return $this->voucherSummary->getDiscountTotal()->add($this->pointsDiscountAmount);
    }

    /**
     * @return string[]
     */
    public function getVoucherCodes(): array
    {
        return array_map(
            static fn(Voucher $voucher): string => $voucher->getCode(),
            $this->voucherSummary->getAppliedVouchers()
        );
    }

    public function getShippingCost(): Money
    {
        return $this->shippingSelection->getCost();
    }

    public function getShippingMethodUid(): int
    {
        return $this->shippingSelection->getShippingMethodUid();
    }
}
