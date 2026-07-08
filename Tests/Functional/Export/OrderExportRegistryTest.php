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

    private OrderExportRegistry $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(OrderExportRegistry::class);
    }

    #[Test]
    public function aThirdPartyExporterIsAutoRegisteredViaTheTaggedIterator(): void
    {
        self::assertSame('dummy', $this->subject->get('dummy')->getIdentifier());
    }

    #[Test]
    public function getAvailableReturnsTheRegisteredExporter(): void
    {
        $identifiers = array_map(
            static fn($exporter): string => $exporter->getIdentifier(),
            $this->subject->getAvailable()
        );

        self::assertContains('dummy', $identifiers);
    }

    #[Test]
    public function theDummyExporterProducesTheExpectedContent(): void
    {
        $order = new Order();
        $order->setOrderNumber('ORD-42');

        self::assertSame('order:ORD-42', $this->subject->get('dummy')->export($order));
    }

    #[Test]
    public function getThrowsExceptionForUnknownIdentifier(): void
    {
        $this->expectException(OrderExporterNotFoundException::class);
        $this->expectExceptionCode(1783900000);

        $this->subject->get('unknown-exporter');
    }
}
