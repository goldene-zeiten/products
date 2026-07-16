<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective Google Pay express configuration for a site by layering the extension configuration
 * under the site's settings via the shared {@see ApiSettingsResolver}, mapping the result onto the typed
 * {@see GooglePayConfiguration}.
 */
final readonly class GooglePayConfigurationFactory
{
    private const EXTENSION_KEY = 'products_express_google_pay';

    private const SETTINGS_PREFIX = 'products.express.googlepay.';

    private const FIELDS = [
        'environment',
        'merchantId',
        'merchantName',
        'countryCode',
        'gateway',
        'gatewayMerchantId',
        'apiBaseUrl',
        'apiKey',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver
    ) {}

    public function forCurrentRequest(): GooglePayConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): GooglePayConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new GooglePayConfiguration(
            environment: strtoupper($value['environment']) === 'PRODUCTION' ? 'PRODUCTION' : 'TEST',
            merchantId: $value['merchantId'],
            merchantName: $value['merchantName'],
            countryCode: strtoupper($value['countryCode']),
            gateway: $value['gateway'],
            gatewayMerchantId: $value['gatewayMerchantId'],
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
            apiKey: $value['apiKey']
        );
    }
}
