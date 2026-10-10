<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\EventListener;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Core\Service\Order\StockService;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Puts the items of a cancelled order back into stock, whoever cancelled it - a failed or abandoned
 * payment, a withdrawal, or the backend order module. A shipped order's goods have left the warehouse, so
 * its cancellation (should a future transition allow one) returns nothing.
 */
#[AsEventListener]
final class ReleaseStockOnOrderCancellationListener
{
    public function __construct(
        private readonly StockService $stockService
    ) {}

    public function __invoke(OrderStatusChangedEvent $event): void
    {
        if ($event->getNewStatus() !== OrderStatus::CANCELLED || $event->getOrderUid() === 0) {
            return;
        }
        if (in_array($event->getPreviousStatus(), [OrderStatus::SHIPPED, OrderStatus::COMPLETED], true)) {
            return;
        }

        $this->stockService->releaseForOrder($event->getOrderUid());
    }
}
