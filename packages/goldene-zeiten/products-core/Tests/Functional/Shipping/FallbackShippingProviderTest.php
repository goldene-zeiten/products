<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderRegistry;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The built-in table-rate carrier is the shop's manual fallback: it must step aside where a real carrier
 * serves the basket, and fill back in where none does. The fixture carrier here serves only Germany, so a
 * German basket is served by it (fallback suppressed) and a French basket is not (fallback fills in).
 */
final class FallbackShippingProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-fallback-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FallbackShippingProviderTest/shipping_methods.csv');
    }

    #[Test]
    public function theManualFallbackStepsAsideWhereARealCarrierServesTheBasket(): void
    {
        $keys = $this->optionKeysForCountry('DE');

        $this->assertContains('countrylimited:standard', $keys);
        $this->assertNotContains('tablerate:1', $keys, 'The manual fallback must step aside where a real carrier serves the basket.');
    }

    #[Test]
    public function theManualFallbackFillsInWhereNoRealCarrierServesTheBasket(): void
    {
        $keys = $this->optionKeysForCountry('FR');

        $this->assertNotContains('countrylimited:standard', $keys, 'The country-limited carrier does not serve France.');
        $this->assertContains('tablerate:1', $keys, 'The manual fallback must fill in where no real carrier can serve the basket.');
    }

    /**
     * @return string[]
     */
    private function optionKeysForCountry(string $countryCode): array
    {
        $context = new ShippingContext(
            [new ShippingContextItem(quantity: 1, weight: 500)],
            500,
            Money::fromCents(10000),
            'EUR',
            $countryCode
        );

        return array_map(
            static fn(mixed $option): string => $option->getKey(),
            $this->get(ShippingProviderRegistry::class)->getAvailableOptions($context)
        );
    }
}
