<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing\Unit;

final readonly class UnitDefinition
{
    public function __construct(
        public string $dimension,
        public float $factorToBase,
        public string $referenceUnit,
        public float $referenceAmountInBase,
    ) {}
}
