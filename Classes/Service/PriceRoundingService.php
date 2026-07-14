<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

/**
 * Applied only at final total-computation points (basket/order gross total) - never to per-line
 * unit prices, to avoid double-rounding or a per-line/total mismatch.
 */
final class PriceRoundingService
{
    public const MODE_NONE = 'none';
    public const MODE_NEAREST_INTEGER = 'nearestInteger';
    public const MODE_PSYCHOLOGICAL_99 = 'psychological99';

    public function round(Money $amount, string $mode): Money
    {
        return match ($mode) {
            self::MODE_NEAREST_INTEGER => $this->roundToNearestInteger($amount),
            self::MODE_PSYCHOLOGICAL_99 => $this->roundToPsychological99($amount),
            default => $amount,
        };
    }

    private function roundToNearestInteger(Money $amount): Money
    {
        return Money::fromCents((int)round($amount->getCents() / 100) * 100);
    }

    /**
     * Nudges the amount to the nearest "...99" charm price: a whole number rounds down to the
     * previous .99 (20.00 -> 19.99), anything else rounds up to the next one (23.45 -> 23.99).
     */
    private function roundToPsychological99(Money $amount): Money
    {
        $cents = $amount->getCents();
        $wholeUnits = intdiv($cents + 99, 100);
        return Money::fromCents(max(0, $wholeUnits * 100 - 1));
    }
}
