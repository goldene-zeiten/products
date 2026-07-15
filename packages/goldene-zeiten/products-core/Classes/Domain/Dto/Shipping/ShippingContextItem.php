<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Shipping;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One line of the basket, as far as a carrier is concerned. A carrier caps parcels per item rather than
 * per basket, and refuses whole classes of goods outright, so it needs the lines and not just a total.
 */
#[Exclude]
final readonly class ShippingContextItem
{
    public function __construct(
        private int $quantity,
        private int $weight,
        private bool $bulky = false,
        private string $shippingClass = ''
    ) {}

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * Weight of a single unit, in grams.
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    public function isBulky(): bool
    {
        return $this->bulky;
    }

    /**
     * What kind of goods this is, as far as shipping is concerned - hazardous, freight-only, refrigerated.
     * The extension defines the field but never interprets it: a carrier matches it against what it is
     * willing to carry, and ignores the classes it does not know.
     */
    public function getShippingClass(): string
    {
        return $this->shippingClass;
    }
}
