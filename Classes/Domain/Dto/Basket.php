<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class Basket
{
    /**
     * @var array<string, BasketItem>
     */
    private array $items = [];

    /**
     * @param array<BasketItem> $items
     */
    public function __construct(array $items = [])
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
    }

    public function addItem(BasketItem $item): void
    {
        $identifier = $this->calculateIdentifier($item->getProductUid(), $item->getArticleUid());
        if (isset($this->items[$identifier])) {
            $existingItem = $this->items[$identifier];
            $item = new BasketItem(
                $item->getProductUid(),
                $item->getArticleUid(),
                $existingItem->getQuantity() + $item->getQuantity()
            );
        }
        $this->items[$identifier] = $item;
    }

    public function updateQuantity(int $productUid, ?int $articleUid, int $quantity): void
    {
        $identifier = $this->calculateIdentifier($productUid, $articleUid);
        if ($quantity <= 0) {
            unset($this->items[$identifier]);
            return;
        }
        $this->items[$identifier] = new BasketItem($productUid, $articleUid, $quantity);
    }

    public function removeItem(int $productUid, ?int $articleUid): void
    {
        $identifier = $this->calculateIdentifier($productUid, $articleUid);
        unset($this->items[$identifier]);
    }

    /**
     * @return array<BasketItem>
     */
    public function getItems(): array
    {
        return array_values($this->items);
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }
        return $count;
    }

    private function calculateIdentifier(int $productUid, ?int $articleUid): string
    {
        return $productUid . ':' . ($articleUid ?? 0);
    }
}
