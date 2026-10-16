<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * One option a customer chose for a basket or order line, e.g. an engraving or a ring size.
 *
 * `value` is what was selected or entered and identifies the choice; `valueLabel` is what the customer
 * reads. The surcharge is per unit: in the shop's pricing mode when a listener of
 * {@see \GoldeneZeiten\Products\Core\Event\ResolveLineItemOptionsEvent} returns it, gross on every basket
 * and order line. A personalizing option makes the line custom-made goods without a withdrawal right.
 */
#[Exclude]
final readonly class LineItemOption
{
    public function __construct(
        private string $identifier,
        private string $label,
        private string $value,
        private string $valueLabel,
        private Money $surcharge,
        private bool $personalizing = false
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string)($data['identifier'] ?? ''),
            (string)($data['label'] ?? ''),
            (string)($data['value'] ?? ''),
            (string)($data['valueLabel'] ?? ''),
            Money::fromCents((int)($data['surcharge'] ?? 0)),
            (bool)($data['personalizing'] ?? false)
        );
    }

    /**
     * @return array{identifier: string, label: string, value: string, valueLabel: string, surcharge: int, personalizing: bool}
     */
    public function toArray(): array
    {
        return [
            'identifier' => $this->identifier,
            'label' => $this->label,
            'value' => $this->value,
            'valueLabel' => $this->valueLabel,
            'surcharge' => $this->surcharge->getCents(),
            'personalizing' => $this->personalizing,
        ];
    }

    public function withSurcharge(Money $surcharge): self
    {
        return new self($this->identifier, $this->label, $this->value, $this->valueLabel, $surcharge, $this->personalizing);
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getValueLabel(): string
    {
        return $this->valueLabel;
    }

    public function getSurcharge(): Money
    {
        return $this->surcharge;
    }

    public function isPersonalizing(): bool
    {
        return $this->personalizing;
    }
}
