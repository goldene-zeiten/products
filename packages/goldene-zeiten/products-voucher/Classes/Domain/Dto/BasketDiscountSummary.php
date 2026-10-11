<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Domain\Dto;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class BasketDiscountSummary
{
    /**
     * @param Voucher[] $appliedVouchers
     * @param Money[] $shares each applied voucher's part of the discount total, in the same order
     */
    public function __construct(
        private array $appliedVouchers,
        private Money $discountTotal,
        private array $shares = []
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
     * What the voucher at this position of {@see getAppliedVouchers()} took off: its own discount, capped at
     * what the vouchers before it left of the goods. The shares add up to the discount total.
     */
    public function getShare(int $position): Money
    {
        return $this->shares[$position] ?? Money::fromCents(0);
    }

    public function isEmpty(): bool
    {
        return $this->appliedVouchers === [];
    }
}
