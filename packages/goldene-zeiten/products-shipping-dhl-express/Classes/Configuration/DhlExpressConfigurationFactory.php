<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective DHL Express configuration for a site. The layering of the system-wide extension
 * configuration under a site's settings is done by the shared {@see ApiSettingsResolver}; this factory
 * only maps the resolved values onto the typed {@see DhlExpressConfiguration} value object, so every consuming
 * service stays free of both the settings source and the request.
 */
final readonly class DhlExpressConfigurationFactory
{
    private const EXTENSION_KEY = 'products_shipping_dhl_express';

    private const SETTINGS_PREFIX = 'products.shipping.dhlexpress.';

    private const FIELDS = [
        'environment',
        'accountNumber',
        'username',
        'password',
        'originCountryCode',
        'originPostCode',
        'originCityName',
        'weightUnit',
        'usedProducts',
        'apiBaseUrl',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver,
    ) {}

    public function forCurrentRequest(): DhlExpressConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): DhlExpressConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new DhlExpressConfiguration(
            environment: DhlExpressEnvironment::fromSetting($value['environment']),
            accountNumber: $value['accountNumber'],
            username: $value['username'],
            password: $value['password'],
            originCountryCode: strtoupper($value['originCountryCode']),
            originPostCode: $value['originPostCode'],
            originCityName: $value['originCityName'],
            weightUnit: strtolower($value['weightUnit']) === 'imperial' ? 'imperial' : 'metric',
            usedProducts: $this->parseProducts($value['usedProducts']),
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
        );
    }

    /**
     * @return string[]
     */
    private function parseProducts(string $csv): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $csv)),
            static fn(string $product): bool => $product !== '',
        ));
    }
}
