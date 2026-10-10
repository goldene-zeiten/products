<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Tests\Unit\Amount;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Stripe\Amount\StripeAmountConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class StripeAmountConverterTest extends UnitTestCase
{
    #[Test]
    #[DataProvider('amountProvider')]
    public function convertsHundredthsIntoTheCurrencysMinorUnit(string $amount, string $currency, int $expected): void
    {
        $subject = new StripeAmountConverter();

        $this->assertSame($expected, $subject->toMinorUnits(Money::fromDecimalString($amount), $currency));
    }

    public static function amountProvider(): \Generator
    {
        yield 'euro cents' => ['amount' => '12.34', 'currency' => 'EUR', 'expected' => 1234];
        yield 'yen in whole units' => ['amount' => '1000', 'currency' => 'JPY', 'expected' => 1000];
        yield 'won in whole units, lower-case code' => ['amount' => '5000', 'currency' => 'krw', 'expected' => 5000];
        yield 'yen rounds a fraction half up' => ['amount' => '1000.50', 'currency' => 'JPY', 'expected' => 1001];
        yield 'dinar in thousandths' => ['amount' => '12.34', 'currency' => 'BHD', 'expected' => 12340];
    }

    #[Test]
    #[DataProvider('decimalsProvider')]
    public function reportsTheDecimalsOfTheCurrencysMinorUnit(string $currency, int $expected): void
    {
        $subject = new StripeAmountConverter();

        $this->assertSame($expected, $subject->decimals($currency));
    }

    public static function decimalsProvider(): \Generator
    {
        yield 'two-decimal' => ['currency' => 'USD', 'expected' => 2];
        yield 'zero-decimal' => ['currency' => 'JPY', 'expected' => 0];
        yield 'three-decimal' => ['currency' => 'OMR', 'expected' => 3];
    }
}
