<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective Stripe configuration for a site. The layering of the system-wide extension
 * configuration under a site's settings is done by the shared {@see ApiSettingsResolver}; this factory
 * only maps the resolved values onto the typed {@see StripeConfiguration} value object, so every consuming
 * service stays free of both the settings source and the request.
 */
final readonly class StripeConfigurationFactory
{
    private const EXTENSION_KEY = 'products_payment_stripe';

    private const SETTINGS_PREFIX = 'products.payment.stripe.';

    private const FIELDS = [
        'secretKey',
        'webhookSecret',
        'apiBaseUrl',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver,
    ) {}

    public function forCurrentRequest(): StripeConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    public function forSite(?Site $site): StripeConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new StripeConfiguration(
            secretKey: $value['secretKey'],
            webhookSecret: $value['webhookSecret'],
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
        );
    }
}
