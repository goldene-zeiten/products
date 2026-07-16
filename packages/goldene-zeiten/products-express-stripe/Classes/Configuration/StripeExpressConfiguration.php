<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Configuration;

use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfiguration;

/**
 * The resolved Stripe Express Checkout configuration for one site. Express needs both a publishable key
 * (the browser renders the Express Checkout Element with it) and a secret key (the server settles the
 * wallet payment via a PaymentIntent) - the redirect Stripe method needs only the secret key, so this is a
 * separate configuration rather than a reuse of {@see StripeConfiguration}.
 */
final readonly class StripeExpressConfiguration
{
    public function __construct(
        public string $publishableKey,
        public string $secretKey,
        public string $apiBaseUrl = ''
    ) {}

    /**
     * The server-side Stripe configuration the shared {@see StripeClientFactory} needs to create the
     * PaymentIntent - the same account, keyed by the secret half.
     */
    public function toStripeConfiguration(): StripeConfiguration
    {
        return new StripeConfiguration($this->secretKey, '', $this->apiBaseUrl);
    }

    /**
     * Express Stripe is offered only when it can both render the button and settle the payment, so a
     * half-configured account keeps the button hidden instead of failing when the customer taps it.
     */
    public function isComplete(): bool
    {
        return $this->publishableKey !== '' && $this->secretKey !== '';
    }
}
