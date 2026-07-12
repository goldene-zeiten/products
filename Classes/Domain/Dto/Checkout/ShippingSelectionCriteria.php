<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

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
