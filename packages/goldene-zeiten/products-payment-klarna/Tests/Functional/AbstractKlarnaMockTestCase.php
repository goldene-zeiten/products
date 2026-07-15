<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Tests\Functional;

use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Payment\Klarna\Client\HttpKlarnaClient;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfiguration;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaEnvironment;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;

/**
 * Base for the Klarna tests that exercise the real HTTP path against the shared WireMock mock. Klarna
 * authenticates with HTTP Basic credentials over the shared {@see ApiHttpClient}; the base URL points at
 * the mock through the resolved configuration.
 */
abstract class AbstractKlarnaMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-payment-klarna',
    ];

    protected function configuration(string $username = 'mock-user', string $password = 'mock-pass'): KlarnaConfiguration
    {
        return new KlarnaConfiguration(
            KlarnaEnvironment::Playground,
            $username,
            $password,
            $this->mockRoot . '/payment/klarna',
        );
    }

    protected function client(): HttpKlarnaClient
    {
        return new HttpKlarnaClient(new ApiHttpClient($this->httpClient()));
    }
}
