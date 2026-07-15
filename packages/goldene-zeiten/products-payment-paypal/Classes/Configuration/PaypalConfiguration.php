<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Configuration;

/**
 * Immutable, request-independent snapshot of the resolved PayPal configuration for one site.
 *
 * Built by {@see PaypalConfigurationFactory} by layering the extension configuration under a site's
 * settings, so every consuming service is free of both the settings source and the request.
 */
final readonly class PaypalConfiguration
{
    public function __construct(
        public PaypalEnvironment $environment,
        public string $clientId,
        public string $clientSecret,
        public string $webhookId,
        public string $brandName,
        public string $apiBaseUrl = '',
    ) {}

    /**
     * Base URL for the PayPal API. Normally derived from the environment; an explicit override lets the
     * calls go through a proxy or a local mock without changing anything else.
     */
    public function baseUrl(): string
    {
        return $this->apiBaseUrl !== '' ? $this->apiBaseUrl : $this->environment->baseUrl();
    }

    /**
     * PayPal is offered only when it can actually create an order, so missing credentials keep the method
     * hidden at checkout instead of failing when the customer picks it.
     */
    public function isComplete(): bool
    {
        return $this->clientId !== '' && $this->clientSecret !== '';
    }
}
