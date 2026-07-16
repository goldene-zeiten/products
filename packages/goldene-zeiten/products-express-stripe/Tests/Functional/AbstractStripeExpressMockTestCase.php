<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Tests\Functional;

use GoldeneZeiten\Products\Express\Stripe\Configuration\StripeExpressConfiguration;
use GoldeneZeiten\Products\Express\Stripe\Payment\StripeExpressPaymentClient;
use GoldeneZeiten\Products\Payment\Stripe\Client\StripeClientFactory;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;
use Psr\Log\NullLogger;

/**
 * Base for the Stripe Express tests that exercise the real PaymentIntent call over Stripe's own SDK against
 * the shared WireMock mock; the API base URL points at the mock through the resolved configuration.
 */
abstract class AbstractStripeExpressMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-payment-stripe',
        'goldene-zeiten/products-express-stripe',
    ];

    protected function configuration(): StripeExpressConfiguration
    {
        return new StripeExpressConfiguration('pk_test', 'sk_test_x', $this->mockRoot . '/express/stripe');
    }

    protected function client(): StripeExpressPaymentClient
    {
        return new StripeExpressPaymentClient(new StripeClientFactory(), new NullLogger());
    }
}
