<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Export;

use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Export\Exception\OrderExporterNotFoundException;
use GoldeneZeiten\Products\Core\Export\OrderExportRegistry;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class OrderExportRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
        'goldene-zeiten/products-export-fixture',
    ];

    #[Test]
    public function dummyExporterIsAutoRegisteredViaTheTaggedIterator(): void
    {
        $subject = $this->get(OrderExportRegistry::class);

        $this->assertSame('dummy', $subject->get('dummy')->getIdentifier());
    }

    #[Test]
    public function getAvailableContainsDummyExporter(): void
    {
        $subject = $this->get(OrderExportRegistry::class);
        $order = new Order();
        $order->setOrderNumber('ORD-42');
        $context = new ExportContext($order);

        $identifiers = array_map(
            static fn($exporter): string => $exporter->getIdentifier(),
            $subject->getAvailable($context)
        );

        $this->assertContains('dummy', $identifiers);
    }

    #[Test]
    public function getAvailableDoesNotContainUnavailableExporter(): void
    {
        $subject = $this->get(OrderExportRegistry::class);
        $order = new Order();
        $order->setOrderNumber('ORD-42');
        $context = new ExportContext($order);

        $identifiers = array_map(
            static fn($exporter): string => $exporter->getIdentifier(),
            $subject->getAvailable($context)
        );

        $this->assertNotContains('unavailable', $identifiers);
    }

    #[Test]
    public function getAvailableReturnHighPriorityExporterBeforeDummyExporter(): void
    {
        $subject = $this->get(OrderExportRegistry::class);
        $order = new Order();
        $order->setOrderNumber('ORD-42');
        $context = new ExportContext($order);

        $identifiers = array_map(
            static fn($exporter): string => $exporter->getIdentifier(),
            $subject->getAvailable($context)
        );

        $this->assertLessThan(
            array_search('dummy', $identifiers, true),
            array_search('high-priority', $identifiers, true),
            'high-priority should come before dummy in the available list'
        );
    }

    #[Test]
    public function contextWithBackendUserUidOneIncludesBeUserBoundExporter(): void
    {
        $subject = $this->get(OrderExportRegistry::class);
        $order = new Order();
        $order->setOrderNumber('ORD-42');
        $context = new ExportContext($order, 1);

        $identifiers = array_map(
            static fn($exporter): string => $exporter->getIdentifier(),
            $subject->getAvailable($context)
        );

        $this->assertContains('be-user-bound', $identifiers);
    }

    #[Test]
    public function contextWithBackendUserUidZeroExcludesBeUserBoundExporter(): void
    {
        $subject = $this->get(OrderExportRegistry::class);
        $order = new Order();
        $order->setOrderNumber('ORD-42');
        $context = new ExportContext($order, 0);

        $identifiers = array_map(
            static fn($exporter): string => $exporter->getIdentifier(),
            $subject->getAvailable($context)
        );

        $this->assertNotContains('be-user-bound', $identifiers);
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
