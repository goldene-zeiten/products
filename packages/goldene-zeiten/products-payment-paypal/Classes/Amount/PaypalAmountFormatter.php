<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Amount;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

/**
 * Formats the shop's amounts, kept in hundredths, as the decimal string PayPal accepts for a currency, and
 * compares amounts by that same rule. Use it for every amount sent to PayPal and every amount read back
 * from PayPal that is checked against an order.
 *
 * PayPal rejects decimals for HUF, JPY and TWD, so those are rounded to whole units; every other supported
 * currency is sent with two decimals.
 *
 * @see https://developer.paypal.com/reference/currency-codes/
 */
final readonly class PaypalAmountFormatter
{
    private const WHOLE_UNIT_CURRENCIES = [
        'HUF', 'JPY', 'TWD',
    ];

    public function format(Money $amount, string $currency): string
    {
        return $this->formatUnits($this->toUnits($amount, $currency), $currency);
    }

    /**
     * The amount in the smallest unit PayPal accepts for the currency: whole units for a currency without
     * decimals, hundredths otherwise. Summing these keeps a breakdown equal to its total.
     */
    public function toUnits(Money $amount, string $currency): int
    {
        return $this->hasDecimals($currency) ? $amount->getCents() : (int)round($amount->getCents() / 100);
    }

    public function formatUnits(int $units, string $currency): string
    {
        return $this->hasDecimals($currency) ? Money::fromCents($units)->getDecimalString() : (string)$units;
    }

    /**
     * Whether two amounts are the same once each is put in the form PayPal works with for the currency.
     */
    public function equals(Money $amount, Money $other, string $currency): bool
    {
        return $this->toUnits($amount, $currency) === $this->toUnits($other, $currency);
    }

    private function hasDecimals(string $currency): bool
    {
        return !in_array(strtoupper($currency), self::WHOLE_UNIT_CURRENCIES, true);
    }
}
