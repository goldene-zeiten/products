<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Discount;

use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Domain\ValueObject\AdjustmentCollection;

/**
 * Builds the immutable view a discount provider decides on, so a provider never reads the basket, the
 * request or the session itself.
 */
final class DiscountContextFactory
{
    /**
     * @param string[] $appliedCodes
     */
    public function createFromBasket(
        BasketViewModel $basketViewModel,
        int $frontendUserUid,
        array $appliedCodes,
        AdjustmentCollection $accumulatedAdjustments
    ): DiscountContext {
        return new DiscountContext(
            $basketViewModel->getTotalGross(),
            $frontendUserUid,
            $appliedCodes,
            $accumulatedAdjustments
        );
    }
}
