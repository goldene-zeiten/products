<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective PayPal configuration for a site. The layering of the system-wide extension
 * configuration under a site's settings is done by the shared {@see ApiSettingsResolver}; this factory
 * only maps the resolved values onto the typed {@see PaypalConfiguration} value object, so every consuming
 * service stays free of both the settings source and the request.
 */
final readonly class PaypalConfigurationFactory
{
    private const EXTENSION_KEY = 'products_payment_paypal';

    private const SETTINGS_PREFIX = 'products.payment.paypal.';

    private const FIELDS = [
        'environment',
        'clientId',
        'clientSecret',
        'webhookId',
        'brandName',
        'apiBaseUrl',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver,
    ) {}

    public function forCurrentRequest(): PaypalConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): PaypalConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new PaypalConfiguration(
            environment: PaypalEnvironment::fromSetting($value['environment']),
            clientId: $value['clientId'],
            clientSecret: $value['clientSecret'],
            webhookId: $value['webhookId'],
            brandName: $value['brandName'],
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
        );
    }
}
