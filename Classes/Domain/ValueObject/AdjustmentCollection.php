<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\ValueObject;

use GoldeneZeiten\Products\Domain\Enum\AdjustmentType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The complete set of money effects on one order, and the single source of truth for its totals. Nothing
 * else may change what the customer pays.
 *
 * Adjustments are held in the order they were contributed, which is the order the providers ran in: a
 * later provider can therefore see - and offset - what an earlier one added, without the two features
 * having to know about each other.
 */
#[Exclude]
final readonly class AdjustmentCollection
{
    /**
     * @var CheckoutAdjustment[]
     */
    private array $adjustments;

    public function __construct(CheckoutAdjustment ...$adjustments)
    {
        $this->adjustments = $adjustments;
    }

    public function with(CheckoutAdjustment $adjustment): self
    {
        return new self(...[...$this->adjustments, $adjustment]);
    }

    /**
     * @return CheckoutAdjustment[]
     */
    public function all(): array
    {
        return $this->adjustments;
    }

    /**
     * @return CheckoutAdjustment[]
     */
    public function byType(AdjustmentType $type): array
    {
        return array_values(array_filter(
            $this->adjustments,
            static fn(CheckoutAdjustment $adjustment): bool => $adjustment->getType() === $type
        ));
    }

    /**
     * Signed sum of every adjustment - what they add to (or take off) the basket's gross total.
     */
    public function getTotal(): Money
    {
        return $this->sum($this->adjustments);
    }

    public function getTotalByType(AdjustmentType $type): Money
    {
        return $this->sum($this->byType($type));
    }

    public function getNetTotal(): Money
    {
        return $this->reduce(
            static fn(CheckoutAdjustment $adjustment): Money => $adjustment->getNetAmount()
        );
    }

    public function getTaxTotal(): Money
    {
        return $this->reduce(
            static fn(CheckoutAdjustment $adjustment): Money => $adjustment->getTaxAmount()
        );
    }

    /**
     * The magnitude of everything that reduced the total, reported as a positive amount - orders record a
     * discount total, not a negative surcharge.
     */
    public function getDiscountTotal(): Money
    {
        $reducing = array_filter(
            $this->adjustments,
            static fn(CheckoutAdjustment $adjustment): bool => $adjustment->getType()->isReducing()
        );
        return Money::fromCents(abs($this->sum($reducing)->getCents()));
    }

    /**
     * @param CheckoutAdjustment[] $adjustments
     */
    private function sum(array $adjustments): Money
    {
        $total = Money::fromCents(0);
        foreach ($adjustments as $adjustment) {
            $total = $total->add($adjustment->getAmount());
        }
        return $total;
    }

    /**
     * @param callable(CheckoutAdjustment): Money $extract
     */
    private function reduce(callable $extract): Money
    {
        $total = Money::fromCents(0);
        foreach ($this->adjustments as $adjustment) {
            $total = $total->add($extract($adjustment));
        }
        return $total;
    }
}
