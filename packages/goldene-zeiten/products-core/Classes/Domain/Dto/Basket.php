<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A line is one product and article with one set of option selections; the same product engraved
 * differently is a line of its own. Lines are addressed by product, article and options key
 * ({@see BasketItem::getOptionsKey()}), which is empty for a line without options.
 */
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
        $identifier = $this->identifierOf($item);
        $quantity = max(1, $item->getQuantity());
        if (isset($this->items[$identifier])) {
            $quantity += $this->items[$identifier]->getQuantity();
        }
        $this->items[$identifier] = $item->withQuantity($quantity);
    }

    /**
     * Sets a line to exactly the item's quantity, adding the line when it is not in the basket yet.
     */
    public function replaceItem(BasketItem $item): void
    {
        $this->items[$this->identifierOf($item)] = $item->withQuantity(max(1, $item->getQuantity()));
    }

    public function getQuantity(int $productUid, ?int $articleUid, string $optionsKey = ''): int
    {
        return ($this->items[$this->calculateIdentifier($productUid, $articleUid, $optionsKey)] ?? null)?->getQuantity() ?? 0;
    }

    /**
     * Changes a line already in the basket; a line that was never added is left out, so it cannot skip
     * the checks adding performs.
     */
    public function updateQuantity(int $productUid, ?int $articleUid, int $quantity, string $optionsKey = ''): void
    {
        $identifier = $this->calculateIdentifier($productUid, $articleUid, $optionsKey);
        if (!isset($this->items[$identifier])) {
            return;
        }
        if ($quantity <= 0) {
            unset($this->items[$identifier]);
            return;
        }
        $this->items[$identifier] = $this->items[$identifier]->withQuantity($quantity);
    }

    public function removeItem(int $productUid, ?int $articleUid, string $optionsKey = ''): void
    {
        unset($this->items[$this->calculateIdentifier($productUid, $articleUid, $optionsKey)]);
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

    private function identifierOf(BasketItem $item): string
    {
        return $this->calculateIdentifier($item->getProductUid(), $item->getArticleUid(), $item->getOptionsKey());
    }

    private function calculateIdentifier(int $productUid, ?int $articleUid, string $optionsKey): string
    {
        return $productUid . ':' . ($articleUid ?? 0) . ':' . $optionsKey;
    }
}
