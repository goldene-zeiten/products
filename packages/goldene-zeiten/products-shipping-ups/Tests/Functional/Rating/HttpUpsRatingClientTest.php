<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\Ups\Authentication\UpsOAuthTokenProvider;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsRatingException;
use GoldeneZeiten\Products\Shipping\Ups\Rating\HttpUpsRatingClient;
use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsRateRequestBuilder;
use GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Fake\FakeHttpClient;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\CacheManager;

final class HttpUpsRatingClientTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-ups',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->get(CacheManager::class)->getCache('products_shipping_ups_token')->flush();
    }

    #[Test]
    public function buildsAShopRequestFromTheContext(): void
    {
        $rateClient = new FakeHttpClient();
        $rateClient->willReturn(FakeHttpClient::jsonResponse(200, $this->rateResponse([['11', '9.99']])));

        $this->subject($rateClient)->rate($this->context(), $this->configuration());

        $request = $rateClient->lastRequest();
        $this->assertSame('https://wwwcie.ups.com/api/rating/v2409/Shop', (string)$request->getUri());
        $this->assertStringStartsWith('Bearer ', $request->getHeaderLine('Authorization'));
        $payload = json_decode((string)$request->getBody(), true);
        $this->assertSame('Shop', $payload['RateRequest']['Request']['RequestOption']);
        $this->assertSame('BE', $payload['RateRequest']['Shipment']['ShipTo']['Address']['CountryCode']);
        $this->assertSame('1000', $payload['RateRequest']['Shipment']['ShipTo']['Address']['PostalCode']);
        $this->assertSame('KGS', $payload['RateRequest']['Shipment']['Package'][0]['PackageWeight']['UnitOfMeasurement']['Code']);
        $this->assertSame('2.5', $payload['RateRequest']['Shipment']['Package'][0]['PackageWeight']['Weight']);
    }

    #[Test]
    public function mapsEveryRatedShipment(): void
    {
        $rateClient = new FakeHttpClient();
        $rateClient->willReturn(FakeHttpClient::jsonResponse(200, $this->rateResponse([['11', '9.99'], ['65', '19.99']])));

        $rates = $this->subject($rateClient)->rate($this->context(), $this->configuration());

        $this->assertCount(2, $rates);
        $this->assertSame('11', $rates[0]->serviceCode);
        $this->assertSame('9.99', $rates[0]->amount);
        $this->assertSame('65', $rates[1]->serviceCode);
    }

    #[Test]
    public function mapsASingleRatedShipmentReturnedAsAnObject(): void
    {
        $rateClient = new FakeHttpClient();
        $single = ['RateResponse' => ['RatedShipment' => [
            'Service' => ['Code' => '11'],
            'TotalCharges' => ['CurrencyCode' => 'EUR', 'MonetaryValue' => '7.50'],
        ]]];
        $rateClient->willReturn(FakeHttpClient::jsonResponse(200, $single));

        $rates = $this->subject($rateClient)->rate($this->context(), $this->configuration());

        $this->assertCount(1, $rates);
        $this->assertSame('11', $rates[0]->serviceCode);
    }

    #[Test]
    public function treatsHttp400AsNoRatesRatherThanAFailure(): void
    {
        $rateClient = new FakeHttpClient();
        $rateClient->willReturn(FakeHttpClient::jsonResponse(400, ['response' => ['errors' => [['code' => '111285', 'message' => 'no rate']]]]));

        $this->assertSame([], $this->subject($rateClient)->rate($this->context(), $this->configuration()));
    }

    #[Test]
    public function retriesOnceWithAFreshTokenAfter401(): void
    {
        $rateClient = new FakeHttpClient();
        $rateClient->willReturn(FakeHttpClient::jsonResponse(401, ['response' => ['errors' => []]]));
        $rateClient->willReturn(FakeHttpClient::jsonResponse(200, $this->rateResponse([['11', '9.99']])));
        $tokenClient = $this->tokenClient(2);

        $rates = $this->subject($rateClient, $tokenClient)->rate($this->context(), $this->configuration());

        $this->assertCount(1, $rates);
        $this->assertCount(2, $rateClient->received, 'The rate request is retried once.');
        $this->assertCount(2, $tokenClient->received, 'A fresh token is fetched for the retry.');
    }

    #[Test]
    public function raisesOnAnUnexpectedStatus(): void
    {
        $rateClient = new FakeHttpClient();
        $rateClient->willReturn(FakeHttpClient::jsonResponse(500, []));

        $this->expectException(UpsRatingException::class);
        $this->subject($rateClient)->rate($this->context(), $this->configuration());
    }

    #[Test]
    public function raisesOnATransportError(): void
    {
        $rateClient = new FakeHttpClient();
        $rateClient->willThrow(FakeHttpClient::transportError('connection reset'));

        $this->expectException(UpsRatingException::class);
        $this->subject($rateClient)->rate($this->context(), $this->configuration());
    }

    private function subject(FakeHttpClient $rateClient, ?FakeHttpClient $tokenClient = null): HttpUpsRatingClient
    {
        $tokenProvider = new UpsOAuthTokenProvider(
            $tokenClient ?? $this->tokenClient(1),
            $this->get(CacheManager::class)->getCache('products_shipping_ups_token'),
        );

        return new HttpUpsRatingClient(
            $rateClient,
            $tokenProvider,
            new UpsRateRequestBuilder(),
            $this->get('Psr\EventDispatcher\EventDispatcherInterface'),
            new NullLogger(),
        );
    }

    private function tokenClient(int $tokens): FakeHttpClient
    {
        $client = new FakeHttpClient();
        for ($i = 1; $i <= $tokens; $i++) {
            $client->willReturn(FakeHttpClient::jsonResponse(200, ['access_token' => 'TOKEN-' . $i, 'expires_in' => '3599']));
        }

        return $client;
    }

    private function context(): ShippingContext
    {
        return new ShippingContext([], 2500, Money::fromCents(5000), 'EUR', 'BE', '1000');
    }

    private function configuration(): UpsConfiguration
    {
        return new UpsConfiguration(UpsEnvironment::Sandbox, 'cid', 'secret', 'ACC', '80331', 'DE', '', 'KGS', []);
    }

    /**
     * @param array<int, array{0: string, 1: string}> $services
     * @return array<string, mixed>
     */
    private function rateResponse(array $services): array
    {
        $ratedShipments = array_map(
            static fn(array $service): array => [
                'Service' => ['Code' => $service[0]],
                'TotalCharges' => ['CurrencyCode' => 'EUR', 'MonetaryValue' => $service[1]],
            ],
            $services,
        );

        return ['RateResponse' => ['RatedShipment' => $ratedShipments]];
    }
}
