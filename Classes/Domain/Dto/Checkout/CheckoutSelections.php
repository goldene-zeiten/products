<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Bundles the raw, unresolved checkout-step choices a shopper brings into checkout - voucher
 * codes, spent points, and the selected shipping method - so they travel as a single argument
 * through OrderPlacementTransaction/OrderCreationService instead of one positional parameter each.
 */
#[Exclude]
final readonly class CheckoutSelections
{
    /**
     * @param string[] $voucherCodes
     */
    public function __construct(
        private array $voucherCodes,
        private int $spendPoints,
        private int $shippingMethodUid = 0
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

    public function getShippingMethodUid(): int
    {
        return $this->shippingMethodUid;
    }
}
