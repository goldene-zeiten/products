<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Shipping;

use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\ShippingProviderRegistry;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ShippingProviderRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-shipping-fixture',
    ];

    #[Test]
    public function fixtureCarrierOptionsAppearAlongsideCoreOptions(): void
    {
        $registry = $this->get(ShippingProviderRegistry::class);
        $context = $this->simpleShippingContext();

        $options = $registry->getAvailableOptions($context);

        $keys = array_map(static fn($option): string => $option->getKey(), $options);
        $this->assertContains('fixture-carrier:standard', $keys);
        $this->assertContains('fixture-carrier:express', $keys);
    }

    #[Test]
    public function oneCarrierContributesBothOfItsOptionsWithCompositeKeys(): void
    {
        $registry = $this->get(ShippingProviderRegistry::class);
        $context = $this->simpleShippingContext();

        $options = $registry->getAvailableOptions($context);

        $fixtureCarrierOptions = array_filter(
            $options,
            static fn($option): bool => str_starts_with($option->getKey(), 'fixture-carrier:')
        );

        $this->assertCount(2, $fixtureCarrierOptions);
        $labels = array_map(static fn($option): string => $option->getLabel(), $fixtureCarrierOptions);
        $this->assertContains('Standard Shipping (Fixture)', $labels);
        $this->assertContains('Express Shipping (Fixture)', $labels);
    }

    #[Test]
    public function compositeKeyResolvesCorrectly(): void
    {
        $registry = $this->get(ShippingProviderRegistry::class);
        $context = $this->simpleShippingContext();

        $option = $registry->resolveOption('fixture-carrier:express', $context);

        $this->assertNotNull($option);
        $this->assertSame('fixture-carrier:express', $option->getKey());
        $this->assertSame(900, $option->getCost()->getCents());
        $this->assertSame('Express Shipping (Fixture)', $option->getLabel());
    }

    #[Test]
    public function invalidOptionIdentifierReturnsNull(): void
    {
        $registry = $this->get(ShippingProviderRegistry::class);
        $context = $this->simpleShippingContext();

        $option = $registry->resolveOption('fixture-carrier:nope', $context);

        $this->assertNull($option);
    }

    #[Test]
    public function unknownProviderReturnsNull(): void
    {
        $registry = $this->get(ShippingProviderRegistry::class);
        $context = $this->simpleShippingContext();

        $option = $registry->resolveOption('gone:x', $context);

        $this->assertNull($option);
    }

    #[Test]
    public function hazmatBasketFiltersOutHazmatRefusingCarrier(): void
    {
        $registry = $this->get(ShippingProviderRegistry::class);

        // Create a basket with a hazmat item
        $hazmatContext = new ShippingContext(
            [
                new ShippingContextItem(quantity: 1, weight: 500, shippingClass: 'hazmat'),
            ],
            500,
            Money::fromCents(10000),
            'EUR',
            'DE'
        );

        $options = $registry->getAvailableOptions($hazmatContext);

        // The hazmat-refusing carrier should not contribute any options
        $hazmatCarrierOptions = array_filter(
            $options,
            static fn($option): bool => str_starts_with($option->getKey(), 'fixture-hazmat:')
        );

        $this->assertEmpty($hazmatCarrierOptions, 'Hazmat-refusing carrier should not contribute options for hazmat baskets.');

        // But other carriers (including the fixture multi-option and tablerate) should still contribute
        $fixtureCarrierOptions = array_filter(
            $options,
            static fn($option): bool => str_starts_with($option->getKey(), 'fixture-carrier:')
        );

        $this->assertNotEmpty($fixtureCarrierOptions, 'Other carriers should still contribute options for hazmat baskets.');
    }

    private function simpleShippingContext(): ShippingContext
    {
        return new ShippingContext(
            [
                new ShippingContextItem(quantity: 1, weight: 500),
            ],
            500,
            Money::fromCents(10000),
            'EUR',
            'DE'
        );
    }
}
