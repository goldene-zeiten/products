<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;

/**
 * Lets integrators customize the invoice PDF before rendering — add company letterhead,
 * custom stamps, or replace it entirely with a custom implementation. Mutable via
 * {@see setReplacementPdf()}, which fully replaces the rendered document.
 *
 * {@see \GoldeneZeiten\Products\Service\Invoice\InvoicePdfService::renderToPdf()}
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
