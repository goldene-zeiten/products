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
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Context\Context;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->get(CacheManager::class)->getCache('products_shipping_dhl_express_rates')->flush();
    }

    /**
     * @param string[] $usedProducts
     */
    protected function configuration(array $usedProducts = [], string $password = 'mock-pass'): DhlExpressConfiguration
    {
        return new DhlExpressConfiguration(
            DhlExpressEnvironment::Sandbox,
            'ACC123',
            'mock-user',
            $password,
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
            new ApiHttpClient($this->requestFactory()),
            new DhlExpressRateRequestBuilder($this->get(Context::class)),
            $this->get(EventDispatcherInterface::class),
            $this->get(CacheManager::class)->getCache('products_shipping_dhl_express_rates'),
            new NullLogger(),
        );
    }
}
