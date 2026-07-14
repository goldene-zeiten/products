<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;

/**
 * Notifies integrators when an order transitions to a new status — send status update emails,
 * update fulfillment systems, or trigger shipment workflows. Fired whenever an order's status
 * changes via the order status manager.
 *
 * @see OrderStatusManager::transition()
 */
final class OrderStatusChangedEvent
{
    public function __construct(
        private readonly Order $order,
        private readonly OrderStatus $previousStatus,
        private readonly OrderStatus $newStatus
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
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
