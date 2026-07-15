<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Tests\Functional;

use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfiguration;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;

/**
 * Base for the Stripe tests that exercise the real HTTP path against the shared WireMock mock. Stripe's
 * own SDK does the HTTP; its API base URL is pointed at the mock through the resolved configuration.
 */
abstract class AbstractStripeMockTestCase extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-payment-stripe',
    ];

    protected function configuration(): StripeConfiguration
    {
        return new StripeConfiguration('sk_test_x', 'whsec_test', $this->mockRoot . '/payment/stripe');
    }
}
