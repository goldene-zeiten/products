<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;

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
