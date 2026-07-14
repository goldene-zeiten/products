<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Configuration\CreditPointsConfiguration;
use GoldeneZeiten\Products\Domain\Dto\CreditPointsRedemption;
use GoldeneZeiten\Products\Domain\Dto\Discount\DiscountContext;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Everything resolved up front for one order placement, so the transactional part does not have to
 * re-resolve it: the placement details handed to the order factory, plus the voucher and credit-point
 * outcomes that still have to be booked once the order exists.
 */
#[Exclude]
final readonly class PlacementContext
{
    public function __construct(
        private PlacementDetails $details,
        private DiscountContext $discountContext,
        private CreditPointsRedemption $pointsRedemption,
        private CreditPointsConfiguration $creditPointsConfiguration,
        private int $frontendUser
    ) {}

    public function getDetails(): PlacementDetails
    {
        return $this->details;
    }

    public function getDiscountContext(): DiscountContext
    {
        return $this->discountContext;
    }

    public function getPointsRedemption(): CreditPointsRedemption
    {
        return $this->pointsRedemption;
    }

    public function getCreditPointsConfiguration(): CreditPointsConfiguration
    {
        return $this->creditPointsConfiguration;
    }

    public function getFrontendUser(): int
    {
        return $this->frontendUser;
    }
}
