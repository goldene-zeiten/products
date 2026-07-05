<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\ValueObject;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class Money
{
    private function __construct(
        private int $cents
    ) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public static function fromDecimalString(string $decimalString): self
    {
        return new self((int)round((float)$decimalString * 100));
    }

    public function getCents(): int
    {
        return $this->cents;
    }

    public function getDecimalString(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiply(float $factor): self
    {
        return new self((int)round($this->cents * $factor));
    }
}
