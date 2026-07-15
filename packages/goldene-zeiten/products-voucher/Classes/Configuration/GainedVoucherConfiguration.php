<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Configuration;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Voucher\Domain\Enum\VoucherDiscountType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class GainedVoucherConfiguration
{
    public function __construct(
        private bool $enabled,
        private Money $minimumOrderValue,
        private VoucherDiscountType $rewardType,
        private string $rewardValue
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getMinimumOrderValue(): Money
    {
        return $this->minimumOrderValue;
    }

    public function getRewardType(): VoucherDiscountType
    {
        return $this->rewardType;
    }

    public function getRewardValue(): string
    {
        return $this->rewardValue;
    }
}
