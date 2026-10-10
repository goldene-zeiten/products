<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Order;

use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What an order charges, split into the lines a summary shows: the goods, every charge and reduction on
 * top, the rounding, and the VAT per rate. The checkout review renders these before the order exists, the
 * order factory writes them onto the order - both from the same calculation, so the review shows the
 * amount that will be charged.
 *
 * Its getters carry the names of the matching {@see Order}
 * properties, so one summary partial renders either.
 */
#[Exclude]
final readonly class OrderTotals
{
    /**
     * @param array<int|string, int> $taxBreakdown VAT cents per rate percentage, e.g. ["19" => 320]
     */
    public function __construct(
        private Money $itemsTotalGross,
        private AdjustmentCollection $adjustments,
        private Money $roundingDifference,
        private Money $totalNet,
        private Money $totalTax,
        private Money $totalGross,
        private array $taxBreakdown
    ) {}

    public function getItemsTotalGross(): Money
    {
        return $this->itemsTotalGross;
    }

    public function getAdjustments(): AdjustmentCollection
    {
        return $this->adjustments;
    }

    public function getShippingTotal(): Money
    {
        return $this->adjustments->getTotalByType(AdjustmentType::SHIPPING);
    }

    public function getHandlingFeeTotal(): Money
    {
        return $this->adjustments->getTotalByType(AdjustmentType::HANDLING);
    }

    public function getDepositTotal(): Money
    {
        return $this->adjustments->getTotalByType(AdjustmentType::DEPOSIT);
    }

    public function getPaymentFeeTotal(): Money
    {
        return $this->adjustments->getTotalByType(AdjustmentType::PAYMENT_FEE);
    }

    /**
     * Everything that lowered the total, as a positive amount.
     */
    public function getDiscountTotal(): Money
    {
        return $this->adjustments->getDiscountTotal();
    }

    /**
     * What the shop's rounding mode added to (or took off) the gross total.
     */
    public function getRoundingDifference(): Money
    {
        return $this->roundingDifference;
    }

    public function getTotalNet(): Money
    {
        return $this->totalNet;
    }

    public function getTotalTax(): Money
    {
        return $this->totalTax;
    }

    public function getTotalGross(): Money
    {
        return $this->totalGross;
    }

    /**
     * @return array<int|string, int>
     */
    public function getTaxBreakdown(): array
    {
        return $this->taxBreakdown;
    }

    /**
     * @return VatLine[]
     */
    public function getVatLines(): array
    {
        $lines = [];
        foreach ($this->taxBreakdown as $percent => $cents) {
            $lines[] = new VatLine((string)$percent, Money::fromCents($cents));
        }
        return $lines;
    }
}
