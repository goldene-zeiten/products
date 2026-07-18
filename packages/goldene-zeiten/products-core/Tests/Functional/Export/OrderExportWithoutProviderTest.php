<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Export;

use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Export\Exception\OrderExporterNotFoundException;
use GoldeneZeiten\Products\Core\Export\OrderExportRegistry;
use GoldeneZeiten\Products\Core\Export\OrderExportService;
use GoldeneZeiten\Products\Core\Tests\Functional\OrderDataTestFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Order export is shop-specific: this extension ships no exporter of its own. These tests deliberately
 * load the extension WITHOUT the export fixture, proving the registry degrades gracefully instead of
 * failing when no integrator has registered anything.
 */
final class OrderExportWithoutProviderTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function theRegistryIsStillConstructableWithoutAnyRegisteredExporter(): void
    {
        $subject = $this->get(OrderExportRegistry::class);

        $this->assertSame([], $subject->getAvailable(new ExportContext($this->order())));
    }

    #[Test]
    public function theServiceOffersNothingWithoutAnyRegisteredExporter(): void
    {
        $subject = $this->get(OrderExportService::class);

        $this->assertSame([], $subject->availableFor(new ExportContext($this->order())));
    }

    #[Test]
    public function resolvingAnExporterThatNoOneRegisteredThrows(): void
    {
        $subject = $this->get(OrderExportRegistry::class);

        $this->expectException(OrderExporterNotFoundException::class);
        $this->expectExceptionCode(1783900000);

        $subject->get('dummy');
    }

    private function order(): OrderData
    {
        return OrderDataTestFactory::minimal('ORD-42');
    }
}
