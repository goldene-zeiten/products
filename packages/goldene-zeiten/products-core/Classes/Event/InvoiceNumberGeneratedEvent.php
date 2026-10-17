<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Service\Order\OrderFactory;

/**
 * Notifies integrators when an invoice number has been assigned — log it, sync it with an
 * accounting system, or publish it to external services. Every order gets its invoice number when
 * it is created, whatever it is paid with; the order is not stored yet at that point - listen to
 * `AfterOrderPlacedEvent` for the stored order.
 *
 * @see OrderFactory::create()
 */
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
