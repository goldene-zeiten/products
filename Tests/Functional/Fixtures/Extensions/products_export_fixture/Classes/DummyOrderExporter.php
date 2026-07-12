<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ExportFixture;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Export\OrderExportInterface;

/**
 * Fixture-only exporter proving OrderExportRegistry's tagged_iterator wiring.
 */
final class DummyOrderExporter implements OrderExportInterface
{
    public function getIdentifier(): string
    {
        return 'dummy';
    }

    public function getLabel(): string
    {
        return 'Dummy Export';
    }

    public function getContentType(): string
    {
        return 'text/plain';
    }

    public function getFileExtension(): string
    {
        return 'txt';
    }

    public function export(Order $order): string
    {
        return sprintf('order:%s', $order->getOrderNumber());
    }
}
