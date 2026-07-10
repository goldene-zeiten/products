<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Bundles ShippingCostService::resolveSelection()'s basket-derived arguments into one object,
 * keeping the method's own parameter count within this project's 5-parameter limit alongside the
 * (also required) ProductsConfiguration and optional ServerRequestInterface.
 */
#[Exclude]
final readonly class ShippingSelectionCriteria
{
    public function __construct(
        private int $shippingMethodUid,
        private BasketViewModel $basketViewModel,
        private string $countryCode,
        private bool $waived
    ) {}

    public function getShippingMethodUid(): int
    {
        return $this->shippingMethodUid;
    }

    public function getBasketViewModel(): BasketViewModel
    {
        return $this->basketViewModel;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function isWaived(): bool
    {
        return $this->waived;
    }
}
