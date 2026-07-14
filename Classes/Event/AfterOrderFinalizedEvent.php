<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Order\OrderFinalizationService;

/**
 * Notifies integrators once an order is fully finalized — push it into an ERP, trigger
 * fulfilment, or notify a warehouse. The order is already persisted, so this is a read-only
 * notification.
 *
 * @see OrderFinalizationService::finalize()
 */
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
