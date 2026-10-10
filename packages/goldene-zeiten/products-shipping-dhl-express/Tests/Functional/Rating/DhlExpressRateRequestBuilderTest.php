<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Functional\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressEnvironment;
use GoldeneZeiten\Products\Shipping\DhlExpress\Rating\DhlExpressRateRequestBuilder;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\DateTimeAspect;

final class DhlExpressRateRequestBuilderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-shipping-dhl-express',
    ];

    /**
     * DHL rates a shipment for the day it is handed over; nothing is handed over on a weekend.
     */
    #[Test]
    #[DataProvider('plannedShippingDateProvider')]
    public function plansShippingForTheNextBusinessDay(string $now, string $expectedDate): void
    {
        $this->get(Context::class)->setAspect('date', new DateTimeAspect(new \DateTimeImmutable($now)));

        $parameters = $this->get(DhlExpressRateRequestBuilder::class)->build($this->context(), $this->configuration());

        $this->assertSame($expectedDate, $parameters['plannedShippingDate']);
    }

    public static function plannedShippingDateProvider(): \Generator
    {
        yield 'a monday ships on tuesday' => ['now' => '2026-10-05 10:00:00', 'expectedDate' => '2026-10-06'];
        yield 'a thursday ships on friday' => ['now' => '2026-10-08 10:00:00', 'expectedDate' => '2026-10-09'];
        yield 'a friday ships on monday' => ['now' => '2026-10-09 10:00:00', 'expectedDate' => '2026-10-12'];
        yield 'a saturday ships on monday' => ['now' => '2026-10-10 10:00:00', 'expectedDate' => '2026-10-12'];
        yield 'a sunday ships on monday' => ['now' => '2026-10-11 10:00:00', 'expectedDate' => '2026-10-12'];
    }

    private function context(): ShippingContext
    {
        return new ShippingContext([], 2500, Money::fromCents(5000), 'EUR', 'BE', '1000');
    }

    private function configuration(): DhlExpressConfiguration
    {
        return new DhlExpressConfiguration(
            DhlExpressEnvironment::Sandbox,
            'ACC123',
            'mock-user',
            'mock-pass',
            'DE',
            '53113',
            'Bonn',
            'metric',
            [],
            'https://dhl.example',
        );
    }
}
