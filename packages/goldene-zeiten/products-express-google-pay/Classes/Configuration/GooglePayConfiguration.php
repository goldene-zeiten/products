<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Configuration;

/**
 * The resolved Google Pay express configuration for one site. Google Pay hands the shop a token encrypted
 * for a tokenization gateway; the shop settles it through a processor it configures itself (base URL plus
 * key), so Google Pay is offered standalone rather than through a fixed provider.
 */
final readonly class GooglePayConfiguration
{
    public function __construct(
        public string $environment,
        public string $merchantId,
        public string $merchantName,
        public string $countryCode,
        public string $gateway,
        public string $gatewayMerchantId,
        public string $apiBaseUrl = '',
        public string $apiKey = ''
    ) {}

    public function processorBaseUrl(): string
    {
        return rtrim($this->apiBaseUrl, '/');
    }

    /**
     * Google Pay is offered only when it can both build the token (a tokenization gateway) and settle it (a
     * processor), so a half-configured account keeps the button hidden instead of failing when tapped.
     */
    public function isComplete(): bool
    {
        return $this->gateway !== '' && $this->gatewayMerchantId !== '' && $this->apiBaseUrl !== '';
    }
}
