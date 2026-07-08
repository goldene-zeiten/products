<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Invoice;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Pure-PHP HTML-to-PDF conversion (dompdf) - no external binary/service needed, so invoice
 * generation works identically on any hosting environment this extension already supports.
 */
final class InvoicePdfService
{
    public function renderToPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return $dompdf->output();
    }
}
