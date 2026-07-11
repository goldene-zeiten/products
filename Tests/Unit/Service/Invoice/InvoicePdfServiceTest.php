<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Service\Invoice;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Event\BeforeInvoiceRenderedEvent;
use GoldeneZeiten\Products\Service\Invoice\InvoicePdfService;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class InvoicePdfServiceTest extends UnitTestCase
{
    #[Test]
    public function renderToPdfProducesANonEmptyPdfDocument(): void
    {
        $pdf = $this->subject()->renderToPdf(new Order(), '<html><body><h1>Invoice</h1></body></html>');

        $this->assertNotSame('', $pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    #[Test]
    public function aListenerCanFullyReplaceTheRenderedPdf(): void
    {
        $eventDispatcher = new class () implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                if ($event instanceof BeforeInvoiceRenderedEvent) {
                    $event->setReplacementPdf('%PDF-REPLACED');
                }
                return $event;
            }
        };

        $pdf = (new InvoicePdfService($eventDispatcher))->renderToPdf(new Order(), '<html></html>');

        $this->assertSame('%PDF-REPLACED', $pdf);
    }

    private function subject(): InvoicePdfService
    {
        $eventDispatcher = new class () implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        };
        return new InvoicePdfService($eventDispatcher);
    }
}
