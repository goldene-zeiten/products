<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface;

/**
 * Fixture carrier for the express-quote tests. It serves only the synthetic country "FX", so loading the
 * fixture extension can never change the shipping options a real-country test computes.
 */
final class FixtureShippingProvider implements ShippingProviderInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-shipping';
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function quote(ShippingContext $context): array
    {
        if ($context->getCountryCode() !== 'FX') {
            return [];
        }

        return [new ShippingOption('fixture-shipping', 'standard', 'Fixture Standard', Money::fromCents(500), null, '2 business days')];
    }

    public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
    {
        foreach ($this->quote($context) as $option) {
            if ($option->getOptionIdentifier() === $optionIdentifier) {
                return $option;
            }
        }

        return null;
    }
}
