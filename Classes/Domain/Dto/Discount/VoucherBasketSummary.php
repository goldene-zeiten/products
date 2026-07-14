<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Discount;

use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What the voucher part of the basket page shows: which codes are applied, what they take off, and what
 * is left to pay. The final total is carried here because the basket template no longer computes it - the
 * voucher owns the discount, so it also owns the total the discount produces.
 */
#[Exclude]
final readonly class VoucherBasketSummary
{
    /**
     * @param Voucher[] $appliedVouchers
     */
    public function __construct(
        private array $appliedVouchers,
        private Money $discountTotal,
        private Money $finalTotal
    ) {}

    /**
     * @return Voucher[]
     */
    public function getAppliedVouchers(): array
    {
        return $this->appliedVouchers;
    }

    public function getDiscountTotal(): Money
    {
        return $this->discountTotal;
    }

    /**
     * What the customer pays once the applied vouchers are taken off.
     */
    public function getFinalTotal(): Money
    {
        return $this->finalTotal;
    }

    public function isEmpty(): bool
    {
        return $this->appliedVouchers === [];
    }
}
