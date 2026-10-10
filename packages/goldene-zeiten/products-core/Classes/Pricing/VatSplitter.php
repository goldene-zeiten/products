<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Domain\ValueObject\VatBuckets;

/**
 * Splits an amount that concerns a whole basket - a voucher, redeemed loyalty points, a rounding
 * difference - across the basket's VAT rates, so it lowers (or raises) net and VAT of each rate in
 * proportion to that rate's share of the gross. The parts are cent-exact and always add up to the amount.
 *
 * Any discount or loyalty provider granting a reduction on the whole basket uses
 * {@see self::splitAdjustment()} to turn it into one taxed adjustment per rate.
 */
final class VatSplitter
{
    /**
     * @return list<array{rate: float, amount: Money}> one part per bucket with a non-zero share
     */
    public function split(Money $amount, VatBuckets $buckets): array
    {
        $totalCents = $buckets->getTotal()->getCents();
        if ($amount->getCents() === 0) {
            return [];
        }
        if ($totalCents <= 0) {
            return [['rate' => 0.0, 'amount' => $amount]];
        }
        $sign = $amount->getCents() < 0 ? -1 : 1;
        $shares = $this->largestRemainderShares(abs($amount->getCents()), $buckets, $totalCents);
        $parts = [];
        foreach ($buckets->all() as $key => $bucket) {
            if ($shares[$key] !== 0) {
                $parts[] = ['rate' => $bucket['rate'], 'amount' => Money::fromCents($sign * $shares[$key])];
            }
        }
        return $parts;
    }

    /**
     * One copy of the adjustment per VAT rate of the buckets, each carrying its share of the amount and
     * taxed at that rate. The adjustment's own tax rate is ignored.
     *
     * @return CheckoutAdjustment[]
     */
    public function splitAdjustment(CheckoutAdjustment $adjustment, VatBuckets $buckets): array
    {
        return array_map(
            static fn(array $part): CheckoutAdjustment => new CheckoutAdjustment(
                $adjustment->getType(),
                $adjustment->getProviderIdentifier(),
                $adjustment->getLabel(),
                $part['amount'],
                $part['rate'],
                $adjustment->getMetadata()
            ),
            $this->split($adjustment->getAmount(), $buckets)
        );
    }

    /**
     * Every bucket first gets the cents its exact share rounds down to; the cents left over go one each to
     * the buckets that lost the most by rounding down, so the shares add up to the amount exactly.
     *
     * @return array<string, int>
     */
    private function largestRemainderShares(int $cents, VatBuckets $buckets, int $totalCents): array
    {
        $shares = [];
        $remainders = [];
        foreach ($buckets->all() as $key => $bucket) {
            $exact = $cents * $bucket['gross']->getCents();
            $shares[$key] = intdiv($exact, $totalCents);
            $remainders[$key] = $exact % $totalCents;
        }
        arsort($remainders);
        $leftOver = $cents - array_sum($shares);
        foreach (array_keys($remainders) as $key) {
            if ($leftOver <= 0) {
                break;
            }
            $shares[$key]++;
            $leftOver--;
        }
        return $shares;
    }
}
