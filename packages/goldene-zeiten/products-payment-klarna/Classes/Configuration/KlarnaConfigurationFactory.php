<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective Klarna configuration for a site. The layering of the system-wide extension
 * configuration under a site's settings is done by the shared {@see ApiSettingsResolver}; this factory
 * only maps the resolved values onto the typed {@see KlarnaConfiguration} value object, so every consuming
 * service stays free of both the settings source and the request.
 */
final readonly class KlarnaConfigurationFactory
{
    private const EXTENSION_KEY = 'products_payment_klarna';

    private const SETTINGS_PREFIX = 'products.payment.klarna.';

    private const FIELDS = [
        'environment',
        'username',
        'password',
        'apiBaseUrl',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver,
    ) {}

    public function forCurrentRequest(): KlarnaConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): KlarnaConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new KlarnaConfiguration(
            environment: KlarnaEnvironment::fromSetting($value['environment']),
            username: $value['username'],
            password: $value['password'],
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
        );
    }
}
