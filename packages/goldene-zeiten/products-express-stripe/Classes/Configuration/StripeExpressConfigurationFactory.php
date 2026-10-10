<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the effective Stripe Express configuration for a site by layering the extension configuration
 * under the site's settings via the shared {@see ApiSettingsResolver}, mapping the result onto the typed
 * {@see StripeExpressConfiguration}.
 */
final readonly class StripeExpressConfigurationFactory
{
    private const EXTENSION_KEY = 'products_express_stripe';

    private const SETTINGS_PREFIX = 'products.express.stripe.';

    private const FIELDS = [
        'publishableKey',
        'secretKey',
        'apiBaseUrl',
    ];

    public function __construct(
        private ApiSettingsResolver $settingsResolver,
        private CurrentSiteResolver $currentSiteResolver
    ) {}

    public function forCurrentRequest(): StripeExpressConfiguration
    {
        return $this->forSite($this->currentSiteResolver->resolve());
    }

    /**
     * The configuration for the site of the given request. Use it wherever the request is at hand - an
     * express callback can run before core publishes the request {@see forCurrentRequest()} reads.
     */
    public function forRequest(ServerRequestInterface $request): StripeExpressConfiguration
    {
        return $this->forSite($this->currentSiteResolver->fromRequest($request));
    }

    public function forSite(?Site $site): StripeExpressConfiguration
    {
        $value = $this->settingsResolver->resolve(self::EXTENSION_KEY, self::SETTINGS_PREFIX, self::FIELDS, $site);

        return new StripeExpressConfiguration(
            publishableKey: $value['publishableKey'],
            secretKey: $value['secretKey'],
            apiBaseUrl: rtrim($value['apiBaseUrl'], '/')
        );
    }
}
