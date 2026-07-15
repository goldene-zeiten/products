<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Tests\Functional;

use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Payment\Paypal\Authentication\PaypalCredentialsFactory;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalEnvironment;
use GoldeneZeiten\Products\Payment\Paypal\Order\HttpPaypalOrderClient;
use GoldeneZeiten\Products\Payment\Paypal\Order\PaypalOrderRequestBuilder;
use GoldeneZeiten\Products\Payment\Paypal\Webhook\PaypalWebhookVerifier;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Base for the PayPal tests that exercise the real HTTP path against the shared WireMock mock. The generic
 * WireMock wiring lives in {@see AbstractApiMockTestCase}; this class adds the PayPal-specific pieces:
 * loading the extension (with the shared api-client it builds on), flushing its token cache, and
 * assembling the order client and webhook verifier pointed at the mock.
 */
abstract class AbstractPaypalMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-payment-paypal',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->get(CacheManager::class)->getCache('products_payment_paypal_token')->flush();
    }

    protected function configuration(string $clientId = 'mock-client', string $webhookId = 'WEBHOOK-OK'): PaypalConfiguration
    {
        return new PaypalConfiguration(
            PaypalEnvironment::Sandbox,
            $clientId,
            'secret',
            $webhookId,
            'Test Shop',
            $this->mockRoot . '/payment/paypal',
        );
    }

    protected function orderClient(): HttpPaypalOrderClient
    {
        $apiHttpClient = new ApiHttpClient($this->httpClient());

        return new HttpPaypalOrderClient(
            $apiHttpClient,
            new OAuth2ClientCredentialsProvider($apiHttpClient, $this->tokenCache()),
            new PaypalCredentialsFactory(),
            new PaypalOrderRequestBuilder(),
            $this->get(EventDispatcherInterface::class),
        );
    }

    protected function webhookVerifier(): PaypalWebhookVerifier
    {
        $apiHttpClient = new ApiHttpClient($this->httpClient());

        return new PaypalWebhookVerifier(
            $apiHttpClient,
            new OAuth2ClientCredentialsProvider($apiHttpClient, $this->tokenCache()),
            new PaypalCredentialsFactory(),
        );
    }

    protected function tokenCache(): FrontendInterface
    {
        return $this->get(CacheManager::class)->getCache('products_payment_paypal_token');
    }
}
