<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Export;

use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Export\Exception\OrderExporterNotAvailableException;
use GoldeneZeiten\Products\Core\Export\Exception\OrderExporterNotFoundException;
use GoldeneZeiten\Products\Core\Export\OrderExportService;
use GoldeneZeiten\Products\Core\Tests\Functional\OrderDataTestFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class OrderExportServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-export-fixture',
    ];

    #[Test]
    public function exportRunsTheSelectedExporterAndDescribesItsPayload(): void
    {
        $subject = $this->get(OrderExportService::class);

        $result = $subject->export('dummy', new ExportContext($this->order()));

        $this->assertSame('order:ORD-42', $result->getPayload());
        $this->assertSame('text/plain', $result->getContentType());
        $this->assertSame('ORD-42.txt', $result->getFileName());
    }

    #[Test]
    public function theSelectedExporterRunsAndNotSomeOtherRegisteredOne(): void
    {
        $subject = $this->get(OrderExportService::class);

        $result = $subject->export('high-priority', new ExportContext($this->order()));

        $this->assertSame('high-priority:ORD-42', $result->getPayload());
    }

    #[Test]
    public function anExporterThatDeniedItselfDuringDiscoveryCannotBeReachedByItsIdentifier(): void
    {
        $subject = $this->get(OrderExportService::class);

        $this->expectException(OrderExporterNotAvailableException::class);
        $this->expectExceptionCode(1784073601);

        $subject->export('unavailable', new ExportContext($this->order()));
    }

    #[Test]
    public function anExporterBoundToAnotherBackendUserCannotBeReachedByItsIdentifier(): void
    {
        $subject = $this->get(OrderExportService::class);

        $this->expectException(OrderExporterNotAvailableException::class);
        $this->expectExceptionCode(1784073601);

        $subject->export('be-user-bound', new ExportContext($this->order(), 0));
    }

    #[Test]
    public function exportThrowsForAnUnknownIdentifier(): void
    {
        $subject = $this->get(OrderExportService::class);

        $this->expectException(OrderExporterNotFoundException::class);
        $this->expectExceptionCode(1783900000);

        $subject->export('unknown-exporter', new ExportContext($this->order()));
    }

    private function order(): OrderData
    {
        return OrderDataTestFactory::minimal('ORD-42');
    }
}
