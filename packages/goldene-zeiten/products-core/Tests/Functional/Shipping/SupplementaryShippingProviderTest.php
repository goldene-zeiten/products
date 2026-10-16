<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderRegistry;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Collecting in store is no carrier: it is offered next to whatever carries the basket, the real carrier
 * or the shop's manual fallback, and replaces neither.
 */
final class SupplementaryShippingProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-fallback-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FallbackShippingProviderTest/shipping_methods.csv');
    }

    /**
     * @param string[] $expectedKeys
     */
    #[Test]
    #[DataProvider('countryProvider')]
    public function collectingIsOfferedNextToWhatCarriesTheBasket(string $countryCode, array $expectedKeys): void
    {
        $options = $this->get(ShippingProviderRegistry::class)->getAvailableOptions($this->context($countryCode));

        $this->assertSame($expectedKeys, array_map(static fn(ShippingOption $option): string => $option->getKey(), $options));
    }

    public static function countryProvider(): \Generator
    {
        yield 'next to a real carrier' => [
            'countryCode' => 'DE',
            'expectedKeys' => ['countrylimited:standard', 'collect:shop'],
        ];
        yield 'next to the manual fallback' => [
            'countryCode' => 'FR',
            'expectedKeys' => ['tablerate:1', 'collect:shop'],
        ];
    }

    #[Test]
    public function aChosenCollectionIsResolved(): void
    {
        $option = $this->get(ShippingProviderRegistry::class)->resolveOption('collect:shop', $this->context('FR'));

        $this->assertSame('collect:shop', $option?->getKey());
    }

    private function context(string $countryCode): ShippingContext
    {
        return new ShippingContext([new ShippingContextItem(quantity: 1, weight: 500)], 500, Money::fromCents(10000), 'EUR', $countryCode);
    }
}
