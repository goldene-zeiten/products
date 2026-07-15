<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\InvoicePaymentMethod;

/**
 * Notifies integrators when an invoice number has been assigned — log it, sync it with an
 * accounting system, or publish it to external services. This fires when invoice payment method
 * is initiated.
 *
 * @see InvoicePaymentMethod::initiate()
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
