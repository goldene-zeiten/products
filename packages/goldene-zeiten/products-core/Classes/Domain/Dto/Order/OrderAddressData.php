<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Order;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Read-only snapshot of an order address, built by {@see OrderDataFactory}.
 */
#[Exclude]
final readonly class OrderAddressData
{
    public function __construct(
        public string $addressType,
        public string $company,
        public string $salutation,
        public string $firstName,
        public string $lastName,
        public string $street,
        public string $houseNumber,
        public string $zip,
        public string $city,
        public string $country,
        public string $telephone,
        public string $vatId,
    ) {}
}
