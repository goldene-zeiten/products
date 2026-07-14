<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Export;

use GoldeneZeiten\Products\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Export\Exception\OrderExporterNotFoundException;
use GoldeneZeiten\Products\Export\OrderExportRegistry;
use GoldeneZeiten\Products\Export\OrderExportService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Order export is shop-specific: this extension ships no exporter of its own. These tests deliberately
 * load the extension WITHOUT the export fixture, proving the registry degrades gracefully instead of
 * failing when no integrator has registered anything.
 */
final class OrderExportWithoutProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

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

    private function order(): Order
    {
        $order = new Order();
        $order->setOrderNumber('ORD-42');
        return $order;
    }
}
