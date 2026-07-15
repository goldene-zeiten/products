<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Discount;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Discount\DiscountContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Builds the immutable view a discount provider decides on: the basket total and customer resolved here,
 * with the request travelling along so a provider can read whatever it alone depends on (a voucher code
 * the customer entered, say) without the core having to know what that is.
 */
final class DiscountContextFactory
{
    public function createFromBasket(
        BasketViewModel $basketViewModel,
        int $frontendUserUid,
        ServerRequestInterface $request,
        AdjustmentCollection $accumulatedAdjustments
    ): DiscountContext {
        return new DiscountContext(
            $basketViewModel->getTotalGross(),
            $frontendUserUid,
            $request,
            $accumulatedAdjustments
        );
    }
}
