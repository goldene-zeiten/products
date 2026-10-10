<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\ValueObject;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Gross amounts grouped by VAT rate - typically the goods of a basket. A reduction on the whole basket is
 * split across these buckets in proportion to their gross.
 */
#[Exclude]
final readonly class VatBuckets
{
    /**
     * @param array<string, array{rate: float, gross: Money}> $buckets keyed by {@see self::key()}
     */
    private function __construct(
        private array $buckets
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * The goods of the basket, one bucket per VAT rate its lines carry.
     */
    public static function fromBasket(BasketViewModel $basketViewModel): self
    {
        $buckets = self::empty();
        foreach ($basketViewModel->getItems() as $item) {
            $buckets = $buckets->with($item->getTaxRate(), $item->getLineTotalGross());
        }
        return $buckets;
    }

    public function with(float $rate, Money $gross): self
    {
        $buckets = $this->buckets;
        $key = self::key($rate);
        $buckets[$key] = [
            'rate' => $rate,
            'gross' => ($buckets[$key]['gross'] ?? Money::fromCents(0))->add($gross),
        ];
        ksort($buckets);
        return new self($buckets);
    }

    /**
     * @return array<string, array{rate: float, gross: Money}> ordered by rate
     */
    public function all(): array
    {
        return $this->buckets;
    }

    public function getTotal(): Money
    {
        $total = Money::fromCents(0);
        foreach ($this->buckets as $bucket) {
            $total = $total->add($bucket['gross']);
        }
        return $total;
    }

    /**
     * A stable key for a rate, so 0.19 from a database decimal and 0.19 from a calculation land in one bucket.
     */
    public static function key(float $rate): string
    {
        return sprintf('%.4F', $rate);
    }
}
