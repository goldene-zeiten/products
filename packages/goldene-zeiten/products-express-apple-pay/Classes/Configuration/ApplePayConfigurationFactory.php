<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective Apple Pay express configuration for a site by layering the extension configuration
 * under the site's settings via the shared {@see ApiSettingsResolver}, mapping the result onto the typed
 * {@see ApplePayConfiguration}.
 */
final readonly class ApplePayConfigurationFactory
{
    private const EXTENSION_KEY = 'products_express_apple_pay';

    private const SETTINGS_PREFIX = 'products.express.applepay.';

    private const FIELDS = [
        'merchantIdentifier',
        'displayName',
        'countryCode',
        'apiBaseUrl',
        'apiKey',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver
    ) {}

    public function forCurrentRequest(): ApplePayConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): ApplePayConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new ApplePayConfiguration(
            merchantIdentifier: $value['merchantIdentifier'],
            displayName: $value['displayName'],
            countryCode: strtoupper($value['countryCode']),
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
            apiKey: $value['apiKey']
        );
    }
}
