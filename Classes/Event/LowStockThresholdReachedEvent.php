<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

final class LowStockThresholdReachedEvent
{
    public function __construct(
        private readonly int $productUid,
        private readonly ?int $articleUid,
        private readonly string $title,
        private readonly int $newStock
    ) {}

    public function getProductUid(): int
    {
        return $this->productUid;
    }

    public function getArticleUid(): ?int
    {
        return $this->articleUid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getNewStock(): int
    {
        return $this->newStock;
    }
}
