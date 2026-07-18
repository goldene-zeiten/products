<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;

/**
 * Notifies integrators when payment status changes — reconcile payment in accounting systems,
 * update customer notifications, or trigger refund workflows. Fired whenever an order's payment
 * status transitions, from the frontend order status manager and from the backend order module alike.
 *
 * Carries the order uid; a listener that needs the full order loads it by that uid.
 *
 * @see OrderStatusManager::transitionPayment()
 */
final class PaymentStatusChangedEvent
{
    public function __construct(
        private readonly int $orderUid,
        private readonly PaymentStatus $previousStatus,
        private readonly PaymentStatus $newStatus
    ) {}

    public function getOrderUid(): int
    {
        return $this->orderUid;
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
