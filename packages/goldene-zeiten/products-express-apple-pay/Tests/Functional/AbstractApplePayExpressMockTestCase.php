<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Tests\Functional;

use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Express\ApplePay\Configuration\ApplePayConfiguration;
use GoldeneZeiten\Products\Express\ApplePay\Payment\ApplePayProcessorClient;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;

/**
 * Base for the Apple Pay express tests that exercise the real processor HTTP calls against the shared
 * WireMock mock. The generic WireMock wiring lives in {@see AbstractApiMockTestCase}; this class loads the
 * extension and assembles the processor client pointed at the mock.
 */
abstract class AbstractApplePayExpressMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-express-apple-pay',
    ];

    protected function configuration(): ApplePayConfiguration
    {
        return new ApplePayConfiguration(
            'merchant.com.test',
            'Test Shop',
            'DE',
            $this->mockRoot . '/express/apple',
            'proc-key'
        );
    }

    protected function processorClient(): ApplePayProcessorClient
    {
        return new ApplePayProcessorClient(new ApiHttpClient($this->httpClient()));
    }
}
