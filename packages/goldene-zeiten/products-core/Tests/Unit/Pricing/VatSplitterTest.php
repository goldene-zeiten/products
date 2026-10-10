<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Pricing;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Domain\ValueObject\VatBuckets;
use GoldeneZeiten\Products\Core\Pricing\VatSplitter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class VatSplitterTest extends UnitTestCase
{
    /**
     * @param array<string, int> $buckets gross cents by rate
     * @param array<string, int> $expected share cents by rate
     */
    #[Test]
    #[DataProvider('splitDataProvider')]
    public function anAmountIsSplitByGrossShareAndAddsUpExactly(int $amount, array $buckets, array $expected): void
    {
        $vatBuckets = VatBuckets::empty();
        foreach ($buckets as $rate => $gross) {
            $vatBuckets = $vatBuckets->with((float)$rate, Money::fromCents($gross));
        }

        $parts = (new VatSplitter())->split(Money::fromCents($amount), $vatBuckets);

        $actual = [];
        foreach ($parts as $part) {
            $actual[VatBuckets::key($part['rate'])] = $part['amount']->getCents();
        }
        $this->assertSame($expected, $actual);
        $this->assertSame($amount, array_sum($actual));
    }

    public static function splitDataProvider(): \Generator
    {
        yield 'an exact split' => [
            'amount' => -1404,
            'buckets' => ['0.19' => 11900, '0.07' => 2140],
            'expected' => ['0.0700' => -214, '0.1900' => -1190],
        ];
        yield 'the left-over cent goes to the largest remainder' => [
            'amount' => -500,
            'buckets' => ['0.19' => 11900, '0.07' => 2140],
            'expected' => ['0.0700' => -76, '0.1900' => -424],
        ];
        yield 'three equal buckets share a cent amount that does not divide' => [
            'amount' => 100,
            'buckets' => ['0.19' => 1000, '0.07' => 1000, '0' => 1000],
            'expected' => ['0.0000' => 34, '0.0700' => 33, '0.1900' => 33],
        ];
        yield 'without goods the amount stays untaxed' => [
            'amount' => -300,
            'buckets' => [],
            'expected' => ['0.0000' => -300],
        ];
        yield 'nothing to split' => [
            'amount' => 0,
            'buckets' => ['0.19' => 1000],
            'expected' => [],
        ];
    }
}
