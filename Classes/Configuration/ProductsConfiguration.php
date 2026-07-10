<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Configuration;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A single, already-resolved snapshot of every products.tax/pricing/shipping/handling setting
 * needed by TaxService/ShippingCostService/HandlingFeeService - see ProductsConfigurationFactory.
 * Passing this in explicitly instead of letting those services read
 * ConfigurationManagerInterface themselves keeps them pure functions of their inputs: trivially
 * constructable in a test with `new ProductsConfiguration(...)`, no request/site-context needed.
 */
#[Exclude]
final readonly class ProductsConfiguration
{
    public function __construct(
        private string $defaultCountry,
        private string $pricingMode,
        private string $currency,
        private bool $shippingEnabled,
        private Money $bulkySurcharge,
        private bool $handlingEnabled
    ) {}

    public function getDefaultCountry(): string
    {
        return $this->defaultCountry;
    }

    public function getPricingMode(): string
    {
        return $this->pricingMode;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isShippingEnabled(): bool
    {
        return $this->shippingEnabled;
    }

    public function getBulkySurcharge(): Money
    {
        return $this->bulkySurcharge;
    }

    public function isHandlingEnabled(): bool
    {
        return $this->handlingEnabled;
    }
}
