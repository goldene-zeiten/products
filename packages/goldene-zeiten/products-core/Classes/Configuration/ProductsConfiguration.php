<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Configuration;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class ProductsConfiguration
{
    public function __construct(
        private string $defaultCountry,
        private string $pricingMode,
        private string $currency,
        private bool $shippingEnabled,
        private Money $bulkySurcharge,
        private bool $handlingEnabled,
        private string $roundingMode,
        private int $priceQuoteValiditySeconds
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

    public function getRoundingMode(): string
    {
        return $this->roundingMode;
    }

    public function getPriceQuoteValiditySeconds(): int
    {
        return $this->priceQuoteValiditySeconds;
    }
}
