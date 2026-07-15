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
     */
    public function __construct(
        private array $appliedVouchers,
        private Money $discountTotal
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

    public function isEmpty(): bool
    {
        return $this->appliedVouchers === [];
    }
}
