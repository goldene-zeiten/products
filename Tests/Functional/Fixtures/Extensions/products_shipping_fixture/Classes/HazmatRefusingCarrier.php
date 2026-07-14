<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ShippingFixture;

use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\ShippingProviderInterface;

/**
 * Fixture shipping provider that refuses to carry hazardous materials, proving context-aware filtering.
 */
final class HazmatRefusingCarrier implements ShippingProviderInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-hazmat';
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function quote(ShippingContext $context): array
    {
        // Refuse to ship if any item has the hazmat shipping class
        if (in_array('hazmat', $context->getShippingClasses(), true)) {
            return [];
        }

        return [
            new ShippingOption(
                $this->getIdentifier(),
                'safe',
                'Safe Shipping (Fixture)',
                Money::fromCents(500)
            ),
        ];
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        // Refuse to ship if any item has the hazmat shipping class
        if (in_array('hazmat', $context->getShippingClasses(), true)) {
            return null;
        }

        if ($optionIdentifier === 'safe') {
            return new ShippingOption(
                $this->getIdentifier(),
                'safe',
                'Safe Shipping (Fixture)',
                Money::fromCents(500)
            );
        }

        return null;
    }
}
