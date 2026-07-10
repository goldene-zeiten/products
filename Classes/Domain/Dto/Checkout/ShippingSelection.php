<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Domain\Model\ShippingMethod;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The resolved outcome of a checkout's shipping-method choice: which method (if any - the
 * feature can be disabled sitewide, or no method chosen yet) and what it finally costs, after
 * any free-shipping voucher waiver has already been applied.
 */
#[Exclude]
final readonly class ShippingSelection
{
    public function __construct(
        private ?ShippingMethod $shippingMethod,
        private Money $cost,
        private float $taxRate = 0.0
    ) {}

    public static function none(): self
    {
        return new self(null, Money::fromCents(0));
    }

    public function getShippingMethod(): ?ShippingMethod
    {
        return $this->shippingMethod;
    }

    public function getShippingMethodUid(): int
    {
        return $this->shippingMethod?->getUid() ?? 0;
    }

    public function getCost(): Money
    {
        return $this->cost;
    }

    /**
     * A fraction (e.g. 0.19 for 19%), the rate the gross shipping cost was/should be reverse-split
     * with - see TaxService::getShippingTaxRate().
     */
    public function getTaxRate(): float
    {
        return $this->taxRate;
    }
}
