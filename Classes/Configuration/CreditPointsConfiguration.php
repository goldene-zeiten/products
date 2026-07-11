<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Configuration;

use GoldeneZeiten\Products\Domain\Dto\CreditPointsEarningTier;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A single, already-resolved snapshot of every products.creditPoints Site Setting needed by
 * CreditPointsService - see CreditPointsConfigurationFactory. Passing this in explicitly instead
 * of letting CreditPointsService read Site Settings itself keeps it a pure function of its inputs,
 * same reasoning as ProductsConfiguration.
 */
#[Exclude]
final readonly class CreditPointsConfiguration
{
    /**
     * @param CreditPointsEarningTier[] $earningTiers
     */
    public function __construct(
        private bool $enabled,
        private Money $moneyPerPoint,
        private string $earningMode,
        private array $earningTiers,
        private float $priceFactor
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getMoneyPerPoint(): Money
    {
        return $this->moneyPerPoint;
    }

    public function getEarningMode(): string
    {
        return $this->earningMode;
    }

    /**
     * @return CreditPointsEarningTier[]
     */
    public function getEarningTiers(): array
    {
        return $this->earningTiers;
    }

    public function getPriceFactor(): float
    {
        return $this->priceFactor;
    }
}
