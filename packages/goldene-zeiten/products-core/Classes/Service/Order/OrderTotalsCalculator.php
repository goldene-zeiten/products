<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderTotals;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\VatLine;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Domain\ValueObject\VatBuckets;
use GoldeneZeiten\Products\Core\Pricing\VatSplitter;
use GoldeneZeiten\Products\Core\Service\PriceRoundingService;

/**
 * Folds the basket and the checkout adjustments into the totals an order charges. Every amount enters
 * with its own net and VAT share at its own rate, and a rounding difference is split across the goods'
 * rates, so the net and VAT totals always add up to the gross total.
 */
final class OrderTotalsCalculator
{
    public function __construct(
        private readonly PriceRoundingService $priceRoundingService,
        private readonly VatSplitter $vatSplitter
    ) {}

    public function calculate(BasketViewModel $basketViewModel, AdjustmentCollection $adjustments, string $roundingMode): OrderTotals
    {
        $goods = VatBuckets::fromBasket($basketViewModel);
        $vat = [];
        foreach ($basketViewModel->getItems() as $item) {
            $vat = $this->addTaxed($vat, $item->getTaxRate(), $item->getLineTotalNet(), $item->getLineTotalTax());
        }
        // The basket's own rounding already moved its gross total away from the sum of its lines.
        $vat = $this->addSplit($vat, $basketViewModel->getTotalGross()->subtract($goods->getTotal()), $goods);
        foreach ($adjustments->all() as $adjustment) {
            $vat = $this->addTaxed($vat, $adjustment->getTaxRate(), $adjustment->getNetAmount(), $adjustment->getTaxAmount());
        }
        $unroundedGross = $basketViewModel->getTotalGross()->add($adjustments->getTotal());
        $totalGross = $this->priceRoundingService->round($unroundedGross, $roundingMode);
        $vat = $this->addSplit($vat, $totalGross->subtract($unroundedGross), $goods);

        return $this->build($goods->getTotal(), $adjustments, $totalGross, $vat);
    }

    /**
     * The rounding difference is what both roundings - the basket's and the order's - together moved the
     * total away from the goods plus the adjustments.
     *
     * @param array<string, array{rate: float, net: Money, tax: Money}> $vat
     */
    private function build(Money $goodsTotal, AdjustmentCollection $adjustments, Money $totalGross, array $vat): OrderTotals
    {
        $totalNet = Money::fromCents(0);
        $totalTax = Money::fromCents(0);
        $taxBreakdown = [];
        foreach (array_reverse($vat) as $line) {
            $totalNet = $totalNet->add($line['net']);
            $totalTax = $totalTax->add($line['tax']);
            if ($line['tax']->getCents() !== 0) {
                $taxBreakdown[VatLine::percentOf($line['rate'])] = $line['tax']->getCents();
            }
        }
        $roundingDifference = $totalGross->subtract($goodsTotal)->subtract($adjustments->getTotal());

        return new OrderTotals($goodsTotal, $adjustments, $roundingDifference, $totalNet, $totalTax, $totalGross, $taxBreakdown);
    }

    /**
     * @param array<string, array{rate: float, net: Money, tax: Money}> $vat
     * @return array<string, array{rate: float, net: Money, tax: Money}>
     */
    private function addSplit(array $vat, Money $amount, VatBuckets $buckets): array
    {
        foreach ($this->vatSplitter->split($amount, $buckets) as $part) {
            $net = $part['amount']->netFromGross($part['rate']);
            $vat = $this->addTaxed($vat, $part['rate'], $net, $part['amount']->subtract($net));
        }
        return $vat;
    }

    /**
     * @param array<string, array{rate: float, net: Money, tax: Money}> $vat
     * @return array<string, array{rate: float, net: Money, tax: Money}>
     */
    private function addTaxed(array $vat, float $rate, Money $net, Money $tax): array
    {
        $key = VatBuckets::key($rate);
        $vat[$key] = [
            'rate' => $rate,
            'net' => ($vat[$key]['net'] ?? Money::fromCents(0))->add($net),
            'tax' => ($vat[$key]['tax'] ?? Money::fromCents(0))->add($tax),
        ];
        ksort($vat);
        return $vat;
    }
}
