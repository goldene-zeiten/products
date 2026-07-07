<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Bundles the two independent discount inputs a shopper can bring into checkout, so they travel
 * as a single argument through OrderPlacementTransaction/OrderCreationService.
 */
#[Exclude]
final readonly class DiscountRequest
{
    /**
     * @param string[] $voucherCodes
     */
    public function __construct(
        private array $voucherCodes,
        private int $spendPoints
    ) {}

    /**
     * @return string[]
     */
    public function getVoucherCodes(): array
    {
        return $this->voucherCodes;
    }

    public function getSpendPoints(): int
    {
        return $this->spendPoints;
    }
}
