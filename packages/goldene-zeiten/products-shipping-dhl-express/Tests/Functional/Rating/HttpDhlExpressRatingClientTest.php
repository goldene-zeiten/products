<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Functional\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\DhlExpress\Exception\DhlExpressRatingException;
use GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Functional\AbstractDhlExpressMockTestCase;
use PHPUnit\Framework\Attributes\Test;

final class HttpDhlExpressRatingClientTest extends AbstractDhlExpressMockTestCase
{
    private const RATES_PATH = '/shipping/dhl-express/rates';

    #[Test]
    public function fetchesAndMapsRatesOverHttp(): void
    {
        $rates = $this->client()->rate($this->context('BE'), $this->configuration());

        $this->assertCount(2, $rates);
        $this->assertSame('P', $rates[0]->productCode);
        $this->assertSame('EXPRESS WORLDWIDE', $rates[0]->productName);
        $this->assertSame('42.5', $rates[0]->amount);
        $this->assertSame('EUR', $rates[0]->currencyCode);
        $this->assertSame('U', $rates[1]->productCode);
    }

    #[Test]
    public function treatsANoRateAnswerAsEmpty(): void
    {
        $this->assertSame([], $this->client()->rate($this->context('XX'), $this->configuration()));
    }

    #[Test]
    public function raisesOnAServerError(): void
    {
        $this->expectException(DhlExpressRatingException::class);
        $this->expectExceptionCode(1752600801);
        $this->client()->rate($this->context('YY'), $this->configuration());
    }

    #[Test]
    public function sendsTheDestinationAndWeightInTheQuery(): void
    {
        $this->client()->rate($this->context('BE'), $this->configuration());

        $url = (string)$this->loggedRequests(self::RATES_PATH, 'GET')[0]['url'];
        $this->assertStringContainsString('destinationCountryCode=BE', $url);
        $this->assertStringContainsString('weight=2.500', $url);
        $this->assertStringContainsString('unitOfMeasurement=metric', $url);
    }

    private function context(string $countryCode): ShippingContext
    {
        return new ShippingContext([], 2500, Money::fromCents(5000), 'EUR', $countryCode, '1000');
    }
}
