<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Catalog;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What a product priced through its variants costs before a variant is chosen: the cheapest and the dearest
 * variant, for a "from" price and a schema.org AggregateOffer.
 */
#[Exclude]
final readonly class VariantPriceRange
{
    public function __construct(
        private Money $lowest,
        private Money $highest,
        private int $variantCount,
        private bool $anyInStock
    ) {}

    public function getLowest(): Money
    {
        return $this->lowest;
    }

    public function getHighest(): Money
    {
        return $this->highest;
    }

    public function getVariantCount(): int
    {
        return $this->variantCount;
    }

    public function isAnyInStock(): bool
    {
        return $this->anyInStock;
    }
}
