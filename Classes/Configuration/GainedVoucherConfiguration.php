<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Configuration;

use GoldeneZeiten\Products\Domain\Enum\VoucherDiscountType;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
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
