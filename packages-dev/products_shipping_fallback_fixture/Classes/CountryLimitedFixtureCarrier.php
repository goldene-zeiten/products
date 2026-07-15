<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ShippingFallbackFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface;

/**
 * A real (non-fallback) carrier that only serves Germany, standing in for "the carrier does not ship to
 * that country". It lets a test prove that the built-in fallback carrier steps aside where this carrier
 * serves the basket and fills back in where it does not.
 */
final class CountryLimitedFixtureCarrier implements ShippingProviderInterface
{
    private const SERVED_COUNTRY = 'DE';

    public function getIdentifier(): string
    {
        return 'countrylimited';
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function quote(ShippingContext $context): array
    {
        if ($context->getCountryCode() !== self::SERVED_COUNTRY) {
            return [];
        }

        return [$this->option()];
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        if ($context->getCountryCode() !== self::SERVED_COUNTRY || $optionIdentifier !== 'standard') {
            return null;
        }

        return $this->option();
    }

    private function option(): ShippingOption
    {
        return new ShippingOption(
            $this->getIdentifier(),
            'standard',
            'Country Limited Standard (Fixture)',
            Money::fromCents(700)
        );
    }
}
