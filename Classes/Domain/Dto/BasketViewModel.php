<?php
declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class BasketViewModel
{
    /**
     * @param array<BasketViewItem> $items
     */
    public function __construct(
        private array $items,
        private Money $totalNet,
        private Money $totalGross,
        private Money $totalTax,
        private string $currency
    ) {}

    /**
     * @return array<BasketViewItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalNet(): Money
    {
        return $this->totalNet;
    }

    public function getTotalGross(): Money
    {
        return $this->totalGross;
    }

    public function getTotalTax(): Money
    {
        return $this->totalTax;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }
        return $count;
    }
}
