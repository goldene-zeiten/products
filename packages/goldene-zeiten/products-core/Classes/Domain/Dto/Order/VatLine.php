<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Order;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The VAT an order or a checkout total contains at one rate, for the "incl. x% VAT" lines of a summary.
 */
#[Exclude]
final readonly class VatLine
{
    public function __construct(
        private string $percent,
        private Money $tax
    ) {}

    /**
     * The rate as a whole percentage without trailing zeros, e.g. "19" or "5.5".
     */
    public function getPercent(): string
    {
        return $this->percent;
    }

    public function getTax(): Money
    {
        return $this->tax;
    }

    /**
     * @param float $rate a fraction, e.g. 0.19 for 19%
     */
    public static function percentOf(float $rate): string
    {
        return rtrim(rtrim(sprintf('%.2F', $rate * 100), '0'), '.');
    }
}
