<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Tests\Functional;

use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Payment\Amazon\Client\HttpAmazonPayClient;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfiguration;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayRegion;
use GoldeneZeiten\Products\Payment\Amazon\Signing\AmazonPaySigner;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;

/**
 * Base for the Amazon Pay tests that exercise the real HTTP path against the shared WireMock mock. The
 * requests are genuinely RSA-signed by the official SDK over the shared {@see ApiHttpClient}; the base URL
 * points at the mock through the resolved configuration. The mock does not verify the signature - it only
 * proves the request is built, signed and sent without error, and that the response is mapped correctly.
 */
abstract class AbstractAmazonPayMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-payment-amazon',
    ];

    protected function configuration(): AmazonPayConfiguration
    {
        return new AmazonPayConfiguration(
            AmazonPayRegion::Eu,
            true,
            'SANDBOX-AMZN-TEST-KEY',
            $this->testPrivateKey(),
            'amzn1.application-oa2-client.test',
            'Test Shop',
            $this->mockRoot . '/payment/amazon',
        );
    }

    protected function client(): HttpAmazonPayClient
    {
        return new HttpAmazonPayClient(new ApiHttpClient($this->httpClient()), new AmazonPaySigner());
    }

    private function testPrivateKey(): string
    {
        return (string)file_get_contents(__DIR__ . '/Fixtures/test_private_key.pem');
    }
}
