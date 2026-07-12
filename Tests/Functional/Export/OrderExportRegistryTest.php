<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Export;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Export\Exception\OrderExporterNotFoundException;
use GoldeneZeiten\Products\Export\OrderExportRegistry;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class OrderExportRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-export-fixture',
    ];

    #[Test]
    public function aThirdPartyExporterIsAutoRegisteredViaTheTaggedIterator(): void
    {
        $subject = $this->get(OrderExportRegistry::class);

        $this->assertSame('dummy', $subject->get('dummy')->getIdentifier());
    }

    #[Test]
    public function getAvailableReturnsTheRegisteredExporter(): void
    {
        $subject = $this->get(OrderExportRegistry::class);

        $identifiers = array_map(
            static fn($exporter): string => $exporter->getIdentifier(),
            $subject->getAvailable()
        );

        $this->assertContains('dummy', $identifiers);
    }

    #[Test]
    public function theDummyExporterProducesTheExpectedContent(): void
    {
        $subject = $this->get(OrderExportRegistry::class);
        $order = new Order();
        $order->setOrderNumber('ORD-42');

        $this->assertSame('order:ORD-42', $subject->get('dummy')->export($order));
    }

    #[Test]
    public function getThrowsExceptionForUnknownIdentifier(): void
    {
        $subject = $this->get(OrderExportRegistry::class);
        $this->expectException(OrderExporterNotFoundException::class);
        $this->expectExceptionCode(1783900000);

        $subject->get('unknown-exporter');
    }
}
