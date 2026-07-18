<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Order;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Read-only snapshot of an order line item, built by {@see OrderDataFactory}.
 */
#[Exclude]
final readonly class OrderItemData
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        public int $uid,
        public int $product,
        public int $article,
        public string $title,
        public string $articleTitle,
        public string $itemNumber,
        public int $quantity,
        public Money $unitPriceNet,
        public Money $unitPriceGross,
        public float $taxRate,
        public Money $lineTotalNet,
        public Money $lineTotalTax,
        public Money $lineTotalGross,
        public Money $depositTotal,
        public array $options,
    ) {}
}
