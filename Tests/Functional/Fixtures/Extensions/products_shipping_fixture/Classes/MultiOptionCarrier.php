<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ShippingFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface;

/**
 * Fixture shipping provider with multiple options, proving tagged_iterator wiring and composite key resolution.
 */
final class MultiOptionCarrier implements ShippingProviderInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-carrier';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function quote(ShippingContext $context): array
    {
        return [
            new ShippingOption(
                $this->getIdentifier(),
                'standard',
                'Standard Shipping (Fixture)',
                Money::fromCents(400)
            ),
            new ShippingOption(
                $this->getIdentifier(),
                'express',
                'Express Shipping (Fixture)',
                Money::fromCents(900)
            ),
        ];
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        return match ($optionIdentifier) {
            'standard' => new ShippingOption(
                $this->getIdentifier(),
                'standard',
                'Standard Shipping (Fixture)',
                Money::fromCents(400)
            ),
            'express' => new ShippingOption(
                $this->getIdentifier(),
                'express',
                'Express Shipping (Fixture)',
                Money::fromCents(900)
            ),
            default => null,
        };
    }
}
