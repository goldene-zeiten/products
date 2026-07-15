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
     * Stripe is offered only when it can actually create a Checkout Session, so a missing secret key keeps
     * the method hidden at checkout instead of failing when the customer picks it.
     */
    public function isComplete(): bool
    {
        return $this->secretKey !== '';
    }
}
