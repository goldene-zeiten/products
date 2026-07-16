<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective Amazon Pay configuration for a site. The layering of the system-wide extension
 * configuration under a site's settings is done by the shared {@see ApiSettingsResolver}; this factory
 * only maps the resolved values onto the typed {@see AmazonPayConfiguration}, so every consuming service
 * stays free of both the settings source and the request.
 */
final readonly class AmazonPayConfigurationFactory
{
    private const EXTENSION_KEY = 'products_payment_amazon';

    private const SETTINGS_PREFIX = 'products.payment.amazon.';

    private const FIELDS = [
        'region',
        'sandbox',
        'publicKeyId',
        'privateKey',
        'storeId',
        'merchantStoreName',
        'apiBaseUrl',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver,
    ) {}

    public function forCurrentRequest(): AmazonPayConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): AmazonPayConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new AmazonPayConfiguration(
            region: AmazonPayRegion::fromSetting($value['region']),
            sandbox: $this->isTrue($value['sandbox']),
            publicKeyId: $value['publicKeyId'],
            privateKey: $value['privateKey'],
            storeId: $value['storeId'],
            merchantStoreName: $value['merchantStoreName'],
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
        );
    }

    private function isTrue(string $value): bool
    {
        return $value === '1' || strtolower($value) === 'true';
    }
}
