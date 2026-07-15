<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Client;

use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfiguration;
use Stripe\StripeClient;

/**
 * Builds a Stripe SDK client from the resolved configuration. The client is per-request rather than a
 * shared service because its API key and base URL depend on the site's configuration, and the base URL
 * is the seam a local mock or proxy is pointed at.
 */
final class StripeClientFactory
{
    public function create(StripeConfiguration $configuration): StripeClient
    {
        return new StripeClient([
            'api_key' => $configuration->secretKey,
            'api_base' => $configuration->baseUrl(),
        ]);
    }
}
