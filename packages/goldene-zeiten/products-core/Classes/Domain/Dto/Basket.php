<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto;

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
        $quantity = max(1, $item->getQuantity());
        if (isset($this->items[$identifier])) {
            $quantity += $this->items[$identifier]->getQuantity();
        }
        $this->items[$identifier] = new BasketItem($item->getProductUid(), $item->getArticleUid(), $quantity);
    }

    /**
     * Sets a line to exactly the item's quantity, adding the line when it is not in the basket yet.
     */
    public function replaceItem(BasketItem $item): void
    {
        $identifier = $this->calculateIdentifier($item->getProductUid(), $item->getArticleUid());
        $this->items[$identifier] = new BasketItem($item->getProductUid(), $item->getArticleUid(), max(1, $item->getQuantity()));
    }

    public function getQuantity(int $productUid, ?int $articleUid): int
    {
        return ($this->items[$this->calculateIdentifier($productUid, $articleUid)] ?? null)?->getQuantity() ?? 0;
    }

    /**
     * Changes a line already in the basket; a line that was never added is left out, so it cannot skip
     * the checks adding performs.
     */
    public function updateQuantity(int $productUid, ?int $articleUid, int $quantity): void
    {
        $identifier = $this->calculateIdentifier($productUid, $articleUid);
        if (!isset($this->items[$identifier])) {
            return;
        }
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
