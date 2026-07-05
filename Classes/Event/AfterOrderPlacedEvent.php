<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;
use Psr\Http\Message\ServerRequestInterface;

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
