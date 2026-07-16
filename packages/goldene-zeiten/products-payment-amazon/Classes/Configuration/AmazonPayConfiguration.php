<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Configuration;

/**
 * Immutable, request-independent snapshot of the resolved Amazon Pay configuration for one site.
 *
 * Built by {@see AmazonPayConfigurationFactory} by layering the extension configuration under a site's
 * settings. Amazon Pay authenticates each request with an RSA signature over a canonical request rather
 * than a static credential; this object carries the key material and the account identifiers the signer
 * and the request bodies need.
 */
final readonly class AmazonPayConfiguration
{
    public function __construct(
        public AmazonPayRegion $region,
        public bool $sandbox,
        public string $publicKeyId,
        public string $privateKey,
        public string $storeId,
        public string $merchantStoreName = '',
        public string $apiBaseUrl = '',
    ) {}

    /**
     * Base URL up to (but excluding) the `/v2` API version. Normally derived from the region and, for a
     * legacy key, the sandbox flag; an explicit override lets the calls go through a proxy or a local mock.
     *
     * A modern, environment-specific key ID (prefixed `LIVE-`/`SANDBOX-`) bakes the environment into the
     * key, so its URL carries no `/live` or `/sandbox` path segment - using a `SANDBOX-` key already routes
     * to the sandbox. A legacy key selects the environment with the path segment instead.
     */
    public function baseUrl(): string
    {
        if ($this->apiBaseUrl !== '') {
            return $this->apiBaseUrl;
        }
        $host = 'https://' . $this->region->apiHost();
        if ($this->hasEnvironmentSpecificKey()) {
            return $host;
        }

        return $host . ($this->sandbox ? '/sandbox' : '/live');
    }

    public function hasEnvironmentSpecificKey(): bool
    {
        $prefix = strtoupper($this->publicKeyId);

        return str_starts_with($prefix, 'LIVE') || str_starts_with($prefix, 'SANDBOX');
    }

    /**
     * Amazon Pay is offered only when it can actually sign a request, so missing key material keeps the
     * method hidden at checkout instead of failing when the customer picks it.
     */
    public function isComplete(): bool
    {
        return $this->publicKeyId !== '' && $this->privateKey !== '' && $this->storeId !== '';
    }
}
