<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing\Unit;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

final readonly class UnitPrice
{
    public function __construct(
        public Money $price,
        public string $referenceUnitLabel,
    ) {}
}
