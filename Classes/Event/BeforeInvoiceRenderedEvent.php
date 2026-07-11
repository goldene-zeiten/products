<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;

/**
 * Dispatched by InvoicePdfService before its own dompdf-based rendering runs - a listener that
 * calls setReplacementPdf() fully replaces the invoice document (e.g. a different PDF engine or a
 * custom layout) without having to reimplement InvoicePdfService/InvoiceRenderer themselves.
 * Mirrors legacy's EXTCONF billdelivery hook array, as an event instead of a hook.
 */
final class BeforeInvoiceRenderedEvent
{
    private ?string $replacementPdf = null;

    public function __construct(
        private readonly Order $order,
        private readonly string $html
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getHtml(): string
    {
        return $this->html;
    }

    public function setReplacementPdf(string $pdf): void
    {
        $this->replacementPdf = $pdf;
    }

    public function getReplacementPdf(): ?string
    {
        return $this->replacementPdf;
    }
}
