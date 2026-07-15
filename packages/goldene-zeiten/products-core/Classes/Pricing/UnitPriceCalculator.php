<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Pricing\Unit\UnitPrice;
use GoldeneZeiten\Products\Core\Pricing\Unit\UnitRegistry;

final class UnitPriceCalculator
{
    public function __construct(
        private readonly UnitRegistry $unitRegistry,
    ) {}

    public function calculate(Money $price, float $contentAmount, string $contentUnit): ?UnitPrice
    {
        if ($contentUnit === '' || $contentAmount <= 0) {
            return null;
        }

        $definition = $this->unitRegistry->get($contentUnit);
        if ($definition === null) {
            return null;
        }

        $totalBaseAmount = $contentAmount * $definition->factorToBase;
        $pricePerBaseUnit = $price->getCents() / $totalBaseAmount;
        $unitPriceCents = (int)round($pricePerBaseUnit * $definition->referenceAmountInBase);

        return new UnitPrice(
            Money::fromCents($unitPriceCents),
            $definition->referenceUnit,
        );
    }
}
