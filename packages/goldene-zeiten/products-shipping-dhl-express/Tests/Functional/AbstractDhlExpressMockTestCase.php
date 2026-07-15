<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Functional;

use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressEnvironment;
use GoldeneZeiten\Products\Shipping\DhlExpress\Rating\DhlExpressRateRequestBuilder;
use GoldeneZeiten\Products\Shipping\DhlExpress\Rating\HttpDhlExpressRatingClient;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;

/**
 * Base for the DHL tests that exercise the real HTTP path against the shared WireMock mock. DHL Express
 * authenticates with HTTP Basic credentials over the shared {@see ApiHttpClient}; the base URL points at
 * the mock through the resolved configuration.
 */
abstract class AbstractDhlExpressMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-shipping-dhl-express',
    ];

    /**
     * @param string[] $usedProducts
     */
    protected function configuration(array $usedProducts = []): DhlExpressConfiguration
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
            $usedProducts,
            $this->mockRoot . '/shipping/dhl-express',
        );
    }

    protected function client(): HttpDhlExpressRatingClient
    {
        return new HttpDhlExpressRatingClient(
            new ApiHttpClient($this->httpClient()),
            new DhlExpressRateRequestBuilder(),
            $this->get(EventDispatcherInterface::class),
            new NullLogger(),
        );
    }
}
