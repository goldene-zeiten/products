<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;

final class AfterOrderFinalizedEvent
{
    public function __construct(
        private readonly Order $order
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }
}
