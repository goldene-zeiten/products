<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\ValueObject;

use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A single money effect on an order total - a shipping cost, a handling fee, a voucher discount, a
 * loyalty redemption, a deposit. This is the only way a feature may change what the customer pays, so
 * an addon never has to reach into the order or into another addon's domain to do it.
 *
 * The amount is signed: a negative amount reduces the total. The label is denormalized on purpose, so an
 * order still renders correctly once the addon that produced the adjustment is uninstalled.
 */
#[Exclude]
final readonly class CheckoutAdjustment
{
    /**
     * @param array<string, string> $metadata provider-private detail (voucher code, points spent, ...)
     */
    public function __construct(
        private AdjustmentType $type,
        private string $providerIdentifier,
        private string $label,
        private Money $amount,
        private float $taxRate = 0.0,
        private array $metadata = []
    ) {}

    public function getType(): AdjustmentType
    {
        return $this->type;
    }

    public function getProviderIdentifier(): string
    {
        return $this->providerIdentifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Only adjustments carrying a tax rate split into a net and a tax share; the others move the gross
     * total alone.
     */
    public function isTaxable(): bool
    {
        return $this->taxRate > 0.0;
    }

    public function getNetAmount(): Money
    {
        return $this->isTaxable() ? $this->amount->netFromGross($this->taxRate) : Money::fromCents(0);
    }

    public function getTaxAmount(): Money
    {
        return $this->isTaxable() ? $this->amount->subtract($this->getNetAmount()) : Money::fromCents(0);
    }
}
