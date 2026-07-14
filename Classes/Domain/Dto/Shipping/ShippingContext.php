<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Shipping;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Everything a carrier may decide on: what is being shipped, how heavy it is, where it is going and who
 * is buying. A carrier can only refuse what it cannot carry if it is told enough to notice, so the lines
 * travel with the totals.
 */
#[Exclude]
final readonly class ShippingContext
{
    /**
     * @param ShippingContextItem[] $items
     */
    public function __construct(
        private array $items,
        private int $totalWeight,
        private Money $goodsTotal,
        private string $currency,
        private string $countryCode,
        private string $postCode = '',
        private int $frontendUserUid = 0
    ) {}

    /**
     * @return ShippingContextItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Total weight of the basket, in grams.
     */
    public function getTotalWeight(): int
    {
        return $this->totalWeight;
    }

    public function getGoodsTotal(): Money
    {
        return $this->goodsTotal;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    /**
     * Carriers price by zone rather than by country, so the postcode matters even when the country does
     * not. Empty when the customer has not entered an address yet.
     */
    public function getPostCode(): string
    {
        return $this->postCode;
    }

    public function getFrontendUserUid(): int
    {
        return $this->frontendUserUid;
    }

    /**
     * @return string[] the distinct shipping classes in this basket, for a carrier to match against what
     *                  it is willing to carry
     */
    public function getShippingClasses(): array
    {
        $classes = array_map(
            static fn(ShippingContextItem $item): string => $item->getShippingClass(),
            $this->items
        );

        return array_values(array_unique(array_filter($classes)));
    }
}
