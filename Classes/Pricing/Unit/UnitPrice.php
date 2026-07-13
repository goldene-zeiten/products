<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing\Unit;

use GoldeneZeiten\Products\Domain\ValueObject\Money;

final readonly class UnitPrice
{
    public function __construct(
        public Money $price,
        public string $referenceUnitLabel,
    ) {}
}
