<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Configuration;

/**
 * Immutable, request-independent snapshot of the resolved Stripe configuration for one site.
 *
 * Built by {@see StripeConfigurationFactory} by layering the extension configuration under a site's
 * settings. Stripe has no separate sandbox host - test vs live is decided by the secret key prefix
 * (`sk_test_` / `sk_live_`), so there is no environment enum, only an optional base-URL override.
 */
final readonly class StripeConfiguration
{
    public function __construct(
        public string $secretKey,
        public string $webhookSecret,
        public string $apiBaseUrl = '',
    ) {}

    /**
     * Base URL for the Stripe API. An explicit override lets the SDK's calls go to a proxy or a local
     * mock without changing anything else; otherwise Stripe's real host is used.
     */
    public function baseUrl(): string
    {
        return $this->apiBaseUrl !== '' ? $this->apiBaseUrl : 'https://api.stripe.com';
    }

    /**
     * Stripe is offered only when it can both create a Checkout Session and verify the confirmation of one:
     * without the webhook secret, {@see \Stripe\Webhook::constructEvent()} can never validate a webhook, so a
     * customer who pays but does not return to the shop would leave the order unconfirmable. A missing key
     * keeps the method hidden at checkout instead of stranding orders.
     */
    public function isComplete(): bool
    {
        return $this->secretKey !== '' && $this->webhookSecret !== '';
    }
}
