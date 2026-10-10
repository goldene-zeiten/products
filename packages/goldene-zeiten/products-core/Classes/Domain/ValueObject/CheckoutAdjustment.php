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
 * The amount is signed: a negative amount reduces the total. It is a gross amount that carries the VAT of
 * its tax rate, so a reduction lowers net and VAT alike; a rate of 0 moves the net alone. The label is
 * denormalized on purpose, so an order still renders correctly once the addon that produced the adjustment
 * is uninstalled.
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
     * The same adjustment, taxed at another rate.
     */
    public function withTaxRate(float $taxRate): self
    {
        return new self($this->type, $this->providerIdentifier, $this->label, $this->amount, $taxRate, $this->metadata);
    }

    /**
     * @return array<string, string>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isTaxable(): bool
    {
        return $this->taxRate > 0.0;
    }

    /**
     * The amount without its VAT; net and VAT always add up to the amount.
     */
    public function getNetAmount(): Money
    {
        return $this->amount->subtract($this->getTaxAmount());
    }

    public function getTaxAmount(): Money
    {
        return $this->isTaxable() ? $this->amount->subtract($this->amount->netFromGross($this->taxRate)) : Money::fromCents(0);
    }
}
