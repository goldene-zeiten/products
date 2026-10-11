<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Configuration;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\PriceRoundingService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the {@see ProductsConfiguration} from the site settings, which are the single source of truth
 * for the shop configuration (the TypoScript settings are mapped from them). Safe to use before page
 * resolution, from middlewares and from listeners on core events.
 */
final class ProductsConfigurationFactory
{
    public function create(ServerRequestInterface $request): ProductsConfiguration
    {
        $site = $request->getAttribute('site');

        return $this->createFromSite($site instanceof Site ? $site : null);
    }

    /**
     * For a context that only knows the site, like the express shipping-rate callback.
     */
    public function createFromSite(?Site $site): ProductsConfiguration
    {
        $siteSettings = $site?->getSettings();

        return new ProductsConfiguration(
            (string)($siteSettings?->get('products.tax.defaultCountry', 'DE') ?? 'DE'),
            (string)($siteSettings?->get('products.pricing.mode', 'gross') ?? 'gross'),
            (string)($siteSettings?->get('products.pricing.currency', 'EUR') ?? 'EUR'),
            (bool)($siteSettings?->get('products.shipping.enabled', false) ?? false),
            Money::fromDecimalString((string)($siteSettings?->get('products.shipping.bulkySurcharge', '0.00') ?? '0.00')),
            (bool)($siteSettings?->get('products.handling.enabled', false) ?? false),
            (string)($siteSettings?->get('products.pricing.roundingMode', PriceRoundingService::MODE_NONE) ?? PriceRoundingService::MODE_NONE),
            (int)($siteSettings?->get('products.checkout.priceQuoteValiditySeconds', 900) ?? 900)
        );
    }
}
