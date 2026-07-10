<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Configuration;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\PriceRoundingService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * The one place in this extension that still resolves settings via ConfigurationManagerInterface -
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

        return new ProductsConfiguration(
            (string)($settings['tax']['defaultCountry'] ?? 'DE'),
            (string)($settings['pricing']['mode'] ?? 'gross'),
            (string)($settings['pricing']['currency'] ?? 'EUR'),
            (bool)($settings['shipping']['enabled'] ?? false),
            Money::fromDecimalString((string)($settings['shipping']['bulkySurcharge'] ?? '0.00')),
            (bool)($settings['handling']['enabled'] ?? false),
            (string)($settings['pricing']['roundingMode'] ?? PriceRoundingService::MODE_NONE)
        );
    }
}
