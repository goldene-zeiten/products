<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Integration;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\Ups\Authentication\UpsOAuthTokenProvider;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment;
use GoldeneZeiten\Products\Shipping\Ups\Rating\HttpUpsRatingClient;
use GoldeneZeiten\Products\Shipping\Ups\Rating\UpsRateRequestBuilder;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Cache\CacheManager;

/**
 * Exercises the real HTTP stack (the container's Guzzle PSR-18 client) against the local Prism mock, so
 * the OAuth-then-rate round trip is proven over the wire without UPS credentials.
 *
 * It is skipped unless the mock's base URL is provided in the UPS_MOCK_BASE_URL environment variable, so
 * it never runs - and never fails - in the normal hermetic suite. Start the mock (see
 * Tests/Mock/docker-compose.yml) and export UPS_MOCK_BASE_URL to run it.
 */
final class UpsMockIntegrationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-ups',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        if ((string)getenv('UPS_MOCK_BASE_URL') === '') {
            $this->markTestSkipped('Set UPS_MOCK_BASE_URL to the running Prism mock to run this test.');
        }
        $this->get(CacheManager::class)->getCache('products_shipping_ups_token')->flush();
    }

    #[Test]
    public function fetchesRatesFromTheMockOverHttp(): void
    {
        $httpClient = $this->get(ClientInterface::class);
        $tokenProvider = new UpsOAuthTokenProvider(
            $httpClient,
            $this->get(CacheManager::class)->getCache('products_shipping_ups_token'),
        );
        $client = new HttpUpsRatingClient(
            $httpClient,
            $tokenProvider,
            new UpsRateRequestBuilder(),
            $this->get(EventDispatcherInterface::class),
            new NullLogger(),
        );

        $rates = $client->rate($this->context(), $this->configuration());

        $this->assertNotSame([], $rates);
        $this->assertSame('11', $rates[0]->serviceCode);
        $this->assertSame('9.99', $rates[0]->amount);
    }

    private function context(): ShippingContext
    {
        return new ShippingContext([], 2500, Money::fromCents(5000), 'EUR', 'BE', '1000');
    }

    private function configuration(): UpsConfiguration
    {
        return new UpsConfiguration(
            UpsEnvironment::Sandbox,
            'mock-client',
            'mock-secret',
            'ACC123',
            '80331',
            'DE',
            '',
            'KGS',
            [],
            (string)getenv('UPS_MOCK_BASE_URL'),
        );
    }
}
