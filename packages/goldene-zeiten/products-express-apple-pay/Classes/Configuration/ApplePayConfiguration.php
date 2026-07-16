<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Configuration;

/**
 * The resolved Apple Pay express configuration for one site. Apple Pay is a card-presentation layer, not a
 * processor, so besides the merchant identity the payment sheet renders with it also needs a processor to
 * validate the merchant session and authorize the encrypted token - kept deliberately gateway-agnostic
 * (base URL plus key) so a shop settles Apple Pay through its own acquirer rather than a fixed provider.
 */
final readonly class ApplePayConfiguration
{
    public function __construct(
        public string $merchantIdentifier,
        public string $displayName,
        public string $countryCode = '',
        public string $apiBaseUrl = '',
        public string $apiKey = ''
    ) {}

    public function processorBaseUrl(): string
    {
        return rtrim($this->apiBaseUrl, '/');
    }

    /**
     * Apple Pay is offered only when it can both render the sheet (merchant identifier) and settle the
     * token (a processor to authorize it), so a half-configured account keeps the button hidden.
     */
    public function isComplete(): bool
    {
        return $this->merchantIdentifier !== '' && $this->apiBaseUrl !== '';
    }
}
