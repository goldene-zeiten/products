<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One priced DHL Express product for a shipment: its code and human name, the charge in the billing
 * currency, and - when DHL provides it - an estimated delivery date/time.
 */
#[Exclude]
final readonly class DhlExpressRate
{
    public function __construct(
        public string $productCode,
        public string $productName,
        public string $amount,
        public string $currencyCode,
        public string $estimatedDelivery = '',
    ) {}
}
