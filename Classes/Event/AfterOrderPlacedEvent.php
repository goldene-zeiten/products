<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Notifies integrators when an order is placed and persisted — send a confirmation email,
 * create a shipping label, or trigger a fulfillment request. The order is ready for processing
 * and the basket has been cleared.
 *
 * @see OrderCreationService::create()
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
