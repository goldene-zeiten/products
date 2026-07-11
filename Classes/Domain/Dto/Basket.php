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
     * @var string[]
     */
    private array $voucherCodes = [];

    /**
     * @param array<BasketItem> $items
     * @param string[] $voucherCodes
     */
    public function __construct(array $items = [], array $voucherCodes = [])
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
        foreach ($voucherCodes as $voucherCode) {
            $this->addVoucherCode($voucherCode);
        }
    }

    /**
     * The incoming quantity is floored at 1 regardless of caller - a negative/zero value must
     * never be able to reduce or wipe out an existing line through this method (that's what
     * updateQuantity()/removeItem() are for).
     */
    public function addItem(BasketItem $item): void
    {
        $identifier = $this->calculateIdentifier($item->getProductUid(), $item->getArticleUid());
        $quantity = max(1, $item->getQuantity());
        if (isset($this->items[$identifier])) {
            $quantity += $this->items[$identifier]->getQuantity();
        }
        $this->items[$identifier] = new BasketItem($item->getProductUid(), $item->getArticleUid(), $quantity);
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

    public function addVoucherCode(string $voucherCode): void
    {
        if (!in_array($voucherCode, $this->voucherCodes, true)) {
            $this->voucherCodes[] = $voucherCode;
        }
    }

    public function removeVoucherCode(string $voucherCode): void
    {
        $this->voucherCodes = array_values(array_filter(
            $this->voucherCodes,
            static fn(string $existing): bool => $existing !== $voucherCode
        ));
    }

    public function clearVoucherCodes(): void
    {
        $this->voucherCodes = [];
    }

    /**
     * @return string[]
     */
    public function getVoucherCodes(): array
    {
        return $this->voucherCodes;
    }
}
