<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Service\Invoice;

use GoldeneZeiten\Products\Service\Invoice\InvoicePdfService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class InvoicePdfServiceTest extends UnitTestCase
{
    #[Test]
    public function renderToPdfProducesANonEmptyPdfDocument(): void
    {
        $pdf = (new InvoicePdfService())->renderToPdf('<html><body><h1>Invoice</h1></body></html>');

        self::assertNotSame('', $pdf);
        self::assertStringStartsWith('%PDF', $pdf);
    }
}
