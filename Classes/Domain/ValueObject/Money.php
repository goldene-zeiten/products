<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\ValueObject;

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

    /**
     * @param float $percent a whole percentage (e.g. 10.0 for 10%), not a fraction
     */
    public function discountByPercent(float $percent): self
    {
        if ($percent <= 0.0) {
            return $this;
        }
        return $this->multiply(1 - $percent / 100);
    }

    /**
     * @param float $taxRate a fraction (e.g. 0.19 for 19%), not a whole percentage
     */
    public function netFromGross(float $taxRate): self
    {
        return new self((int)round($this->cents / (1 + $taxRate)));
    }
}
