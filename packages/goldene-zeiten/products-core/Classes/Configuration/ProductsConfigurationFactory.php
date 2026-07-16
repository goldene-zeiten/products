<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Configuration;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\PriceRoundingService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * Some settings come from legacy TypoScript via ConfigurationManagerInterface, others from
 * TYPO3 v13 Site Settings (read via request's site attribute) since ConfigurationManagerInterface
 * cannot access them.
 */
final class ProductsConfigurationFactory
{
    public function __construct(
        private readonly ConfigurationManagerInterface $configurationManager
    ) {}

    public function create(ServerRequestInterface $request): ProductsConfiguration
    {
        $this->configurationManager->setRequest($request);
        $settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'ProductsCore'
        );
        $siteSettings = $request->getAttribute('site')?->getSettings();

        return new ProductsConfiguration(
            (string)($settings['tax']['defaultCountry'] ?? 'DE'),
            (string)($settings['pricing']['mode'] ?? 'gross'),
            (string)($settings['pricing']['currency'] ?? 'EUR'),
            (bool)($siteSettings?->get('products.shipping.enabled', false) ?? false),
            Money::fromDecimalString((string)($siteSettings?->get('products.shipping.bulkySurcharge', '0.00') ?? '0.00')),
            (bool)($siteSettings?->get('products.handling.enabled', false) ?? false),
            (string)($siteSettings?->get('products.pricing.roundingMode', PriceRoundingService::MODE_NONE) ?? PriceRoundingService::MODE_NONE),
            (int)($siteSettings?->get('products.checkout.priceQuoteValiditySeconds', 900) ?? 900)
        );
    }

    /**
     * A configuration built from site settings alone, for a stateless context that runs before page
     * resolution and so has no TypoScript - the express shipping-rate callback. The TypoScript-derived
     * fields (default country, pricing mode, presentment currency) fall back to their defaults; the
     * express quote does not read them (the currency travels with the basket), only the site-settings
     * fields shipping needs.
     */
    public function createFromSite(?Site $site): ProductsConfiguration
    {
        $siteSettings = $site?->getSettings();

        return new ProductsConfiguration(
            'DE',
            'gross',
            'EUR',
            (bool)($siteSettings?->get('products.shipping.enabled', false) ?? false),
            Money::fromDecimalString((string)($siteSettings?->get('products.shipping.bulkySurcharge', '0.00') ?? '0.00')),
            (bool)($siteSettings?->get('products.handling.enabled', false) ?? false),
            (string)($siteSettings?->get('products.pricing.roundingMode', PriceRoundingService::MODE_NONE) ?? PriceRoundingService::MODE_NONE),
            (int)($siteSettings?->get('products.checkout.priceQuoteValiditySeconds', 900) ?? 900)
        );
    }
}
