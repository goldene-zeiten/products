<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Amount;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

/**
 * Converts the shop's amounts, which are kept in hundredths of the currency unit, into the integer minor
 * unit Stripe expects for that currency. Use it for every amount sent to Stripe and for every amount read
 * back from Stripe that is compared against an order.
 *
 * Stripe counts zero-decimal currencies (JPY, KRW, ...) in whole units and three-decimal currencies (BHD,
 * KWD, ...) in thousandths; every other currency in hundredths.
 *
 * @see https://docs.stripe.com/currencies#zero-decimal
 */
final readonly class StripeAmountConverter
{
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF',
        'XPF',
    ];

    private const THREE_DECIMAL_CURRENCIES = [
        'BHD', 'JOD', 'KWD', 'OMR', 'TND',
    ];

    public function toMinorUnits(Money $amount, string $currency): int
    {
        return match ($this->decimals($currency)) {
            0 => (int)round($amount->getCents() / 100),
            3 => $amount->getCents() * 10,
            default => $amount->getCents(),
        };
    }

    /**
     * The inverse of {@see toMinorUnits()}: an amount Stripe reports back, as the shop's hundredths.
     */
    public function fromMinorUnits(int $minorUnits, string $currency): Money
    {
        return Money::fromCents(match ($this->decimals($currency)) {
            0 => $minorUnits * 100,
            3 => (int)round($minorUnits / 10),
            default => $minorUnits,
        });
    }

    /**
     * The number of decimals Stripe's minor unit has for the currency, for a frontend that has to convert
     * the shop's hundredths itself.
     */
    public function decimals(string $currency): int
    {
        $currency = strtoupper($currency);
        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return 0;
        }

        return in_array($currency, self::THREE_DECIMAL_CURRENCIES, true) ? 3 : 2;
    }
}
