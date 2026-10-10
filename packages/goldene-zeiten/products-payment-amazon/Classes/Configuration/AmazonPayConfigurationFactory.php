<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use Psr\Http\Message\ServerRequestInterface;
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
        'environment',
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

    /**
     * The configuration for the site of the given request. Use it wherever the request is at hand - a
     * gateway callback runs before the page is resolved, where {@see forCurrentRequest()} finds no site.
     */
    public function forRequest(ServerRequestInterface $request): AmazonPayConfiguration
    {
        return $this->forSite($this->currentSiteResolver->fromRequest($request));
    }

    public function forSite(?Site $site): AmazonPayConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new AmazonPayConfiguration(
            region: AmazonPayRegion::fromSetting($value['region']),
            sandbox: strtolower($value['environment']) !== 'live',
            publicKeyId: $value['publicKeyId'],
            privateKey: $value['privateKey'],
            storeId: $value['storeId'],
            merchantStoreName: $value['merchantStoreName'],
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/'),
        );
    }
}
