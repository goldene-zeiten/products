<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Discount;

use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Everything a discount provider may base a discount on: the goods total it applies to, the customer, any
 * codes the customer entered, and the adjustments accumulated so far.
 *
 * The accumulated adjustments are what let a discount offset an earlier charge without knowing where it
 * came from - a free-shipping discount negates the shipping adjustment it finds in here rather than
 * calling into shipping. Discounts therefore run after the charges they may offset.
 */
#[Exclude]
final readonly class DiscountContext
{
    /**
     * @param string[] $appliedCodes codes the customer entered at checkout, e.g. voucher codes
     */
    public function __construct(
        private Money $goodsTotal,
        private int $frontendUserUid,
        private array $appliedCodes,
        private AdjustmentCollection $accumulatedAdjustments
    ) {}

    public function getGoodsTotal(): Money
    {
        return $this->goodsTotal;
    }

    public function getFrontendUserUid(): int
    {
        return $this->frontendUserUid;
    }

    /**
     * @return string[]
     */
    public function getAppliedCodes(): array
    {
        return $this->appliedCodes;
    }

    public function getAccumulatedAdjustments(): AdjustmentCollection
    {
        return $this->accumulatedAdjustments;
    }
}
