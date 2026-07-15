<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Shipping;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The shipping the customer settled on: the carrier's option, what it costs after the shop's own
 * discounts, and what the shop charges on top.
 *
 * The carrier's rate and the shop's surcharge stay apart on purpose. A free-shipping voucher waives what
 * the carrier charges; it does not waive the handling of an oversized item, which costs the shop the same
 * whoever pays for the transport.
 */
#[Exclude]
final readonly class ShippingSelection
{
    public function __construct(
        private ?ShippingOption $option,
        private Money $carrierCost,
        private Money $surcharge,
        private float $taxRate = 0.0
    ) {}

    public static function none(): self
    {
        return new self(null, Money::fromCents(0), Money::fromCents(0));
    }

    public function getOption(): ?ShippingOption
    {
        return $this->option;
    }

    public function getKey(): string
    {
        return $this->option?->getKey() ?? '';
    }

    public function getLabel(): string
    {
        return $this->option?->getLabel() ?? '';
    }

    /**
     * What the carrier charges, after the shop's frontend-usergroup discount.
     */
    public function getCarrierCost(): Money
    {
        return $this->carrierCost;
    }

    /**
     * What the shop adds on top of the carrier's rate - today, the bulky-goods surcharge.
     */
    public function getSurcharge(): Money
    {
        return $this->surcharge;
    }

    public function getTotal(): Money
    {
        return $this->carrierCost->add($this->surcharge);
    }

    /**
     * A fraction, e.g. 0.19 for 19%.
     */
    public function getTaxRate(): float
    {
        return $this->taxRate;
    }
}
