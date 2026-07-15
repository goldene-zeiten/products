<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;

/**
 * Notifies integrators when stock falls below the configured threshold — send an alert to
 * the warehouse, trigger an automatic reorder, or block further sales of the item. Fired
 * during order placement when stock is decremented.
 *
 * @see OrderCreationService::dispatchLowStockEvent()
 */
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
