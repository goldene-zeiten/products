<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Notifies integrators when an order is placed and persisted — send a confirmation email,
 * create a shipping label, or trigger a fulfillment request. The order is ready for processing
 * and the basket has been cleared.
 *
 * {@see \GoldeneZeiten\Products\Service\Order\OrderCreationService::create()}
 */
final class AfterOrderPlacedEvent
{
    public function __construct(
        private readonly Order $order,
        private readonly ServerRequestInterface $request
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
