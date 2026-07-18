<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;

/**
 * Notifies integrators when an order transitions to a new status — send status update emails,
 * update fulfillment systems, or trigger shipment workflows. Fired whenever an order's status
 * changes, from the frontend order status manager and from the backend order module alike.
 *
 * Carries the order uid; a listener that needs the full order loads it by that uid.
 *
 * @see OrderStatusManager::transition()
 */
final class OrderStatusChangedEvent
{
    public function __construct(
        private readonly int $orderUid,
        private readonly OrderStatus $previousStatus,
        private readonly OrderStatus $newStatus
    ) {}

    public function getOrderUid(): int
    {
        return $this->orderUid;
    }

    public function getPreviousStatus(): OrderStatus
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): OrderStatus
    {
        return $this->newStatus;
    }
}
