<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Configuration;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\PriceRoundingService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * `defaultCountry`/`pricingMode`/`currency` are the only fields still resolved via
 * ConfigurationManagerInterface - legacy TypoScript (Configuration/TypoScript/{constants,setup}.
 * typoscript) genuinely bridges those into plugin.tx_products.settings. `shipping`/`handling`/
 * `pricing.roundingMode` are TYPO3 v13 Site Settings (Configuration/Sets/Products/
 * settings.definitions.yaml) that ConfigurationManagerInterface can never see - no such bridge
 * exists for them - so they're read straight from the request's `site` attribute instead, the
 * same mechanism WishlistService/WishlistStorage/StorageFolderResolver already use.
 * TaxService/ShippingCostService/HandlingFeeService take the resulting plain ProductsConfiguration
 * value object as an explicit parameter instead of reading settings themselves. Called by whoever
 * naturally has a request in hand (a controller action, or a service that already receives one,
 * e.g. OrderCreationService::create()) - never resolved eagerly in a constructor, which is what
 * made those services' behaviour depend on incidental construction order/timing rather than being
 * a pure function of their inputs.
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
            'Products'
        );
        $siteSettings = $request->getAttribute('site')?->getSettings();

        return new ProductsConfiguration(
            (string)($settings['tax']['defaultCountry'] ?? 'DE'),
            (string)($settings['pricing']['mode'] ?? 'gross'),
            (string)($settings['pricing']['currency'] ?? 'EUR'),
            (bool)($siteSettings?->get('products.shipping.enabled', false) ?? false),
            Money::fromDecimalString((string)($siteSettings?->get('products.shipping.bulkySurcharge', '0.00') ?? '0.00')),
            (bool)($siteSettings?->get('products.handling.enabled', false) ?? false),
            (string)($siteSettings?->get('products.pricing.roundingMode', PriceRoundingService::MODE_NONE) ?? PriceRoundingService::MODE_NONE)
        );
    }
}
