<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;

/**
 * Notifies integrators when payment status changes — reconcile payment in accounting systems,
 * update customer notifications, or trigger refund workflows. Fired whenever an order's payment
 * status transitions via the order status manager.
 *
 * @see OrderStatusManager::transitionPayment()
 */
final class PaymentStatusChangedEvent
{
    public function __construct(
        private readonly Order $order,
        private readonly PaymentStatus $previousStatus,
        private readonly PaymentStatus $newStatus
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getPreviousStatus(): PaymentStatus
    {
        return $this->previousStatus;
    }

    public function getNewStatus(): PaymentStatus
    {
        return $this->newStatus;
    }
}
