<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Tests\Functional;

use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Express\GooglePay\Configuration\GooglePayConfiguration;
use GoldeneZeiten\Products\Express\GooglePay\Payment\GooglePayProcessorClient;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;

/**
 * Base for the Google Pay express tests that exercise the real processor HTTP call against the shared
 * WireMock mock. The generic WireMock wiring lives in {@see AbstractApiMockTestCase}; this class loads the
 * extension and assembles the processor client pointed at the mock.
 */
abstract class AbstractGooglePayExpressMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-express-google-pay',
    ];

    protected function configuration(): GooglePayConfiguration
    {
        return new GooglePayConfiguration(
            'TEST',
            'merchant-1234',
            'Test Shop',
            'DE',
            'exampleGateway',
            'gateway-merchant-1',
            $this->mockRoot . '/express/google',
            'proc-key'
        );
    }

    protected function processorClient(): GooglePayProcessorClient
    {
        return new GooglePayProcessorClient(new ApiHttpClient($this->httpClient()));
    }
}
