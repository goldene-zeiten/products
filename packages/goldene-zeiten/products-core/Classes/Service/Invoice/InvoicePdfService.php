<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Invoice;

use Dompdf\Dompdf;
use Dompdf\Options;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Event\BeforeInvoiceRenderedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

final class InvoicePdfService
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function renderToPdf(Order $order, string $html): string
    {
        $event = new BeforeInvoiceRenderedEvent($order, $html);
        $this->eventDispatcher->dispatch($event);
        if ($event->getReplacementPdf() !== null) {
            return $event->getReplacementPdf();
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
