<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;

/**
 * A listener calling {@see setReplacementPdf()} fully replaces the rendered invoice document.
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
