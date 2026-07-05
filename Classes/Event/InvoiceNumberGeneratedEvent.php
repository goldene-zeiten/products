<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;

final class InvoiceNumberGeneratedEvent
{
    public function __construct(
        private readonly Order $order,
        private readonly string $invoiceNumber
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }
}
