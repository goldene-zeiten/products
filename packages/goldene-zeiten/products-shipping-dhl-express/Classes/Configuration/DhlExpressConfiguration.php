<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Configuration;

/**
 * Immutable, request-independent snapshot of the resolved DHL Express configuration for one site.
 *
 * Built by {@see DhlExpressConfigurationFactory} by layering the extension configuration under a site's settings.
 * DHL Express authenticates with HTTP Basic credentials (the MyDHL API key and secret).
 */
final readonly class DhlExpressConfiguration
{
    /**
     * @param string[] $usedProducts DHL product codes to offer; empty offers every product DHL returns
     */
    public function __construct(
        public DhlExpressEnvironment $environment,
        public string $accountNumber,
        public string $username,
        public string $password,
        public string $originCountryCode,
        public string $originPostCode,
        public string $originCityName,
        public string $weightUnit,
        public array $usedProducts,
        public string $apiBaseUrl = '',
    ) {}

    public function baseUrl(): string
    {
        return $this->apiBaseUrl !== '' ? $this->apiBaseUrl : $this->environment->baseUrl();
    }

    public function authorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->password);
    }

    /**
     * Rating is attempted only when the credentials and origin needed for a quote are present. Anything
     * missing keeps the carrier silent, so the table-rate fallback serves the basket instead.
     */
    public function isComplete(): bool
    {
        return $this->username !== ''
            && $this->password !== ''
            && $this->originCountryCode !== ''
            && $this->originCityName !== '';
    }

    /**
     * Whether the given DHL product code should be offered under the configured allow-list.
     */
    public function offersProduct(string $productCode): bool
    {
        return $this->usedProducts === [] || in_array($productCode, $this->usedProducts, true);
    }
}
