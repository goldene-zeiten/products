<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ExportFixture;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Export\OrderExportInterface;

/**
 * Proves OrderExportRegistry's tagged_iterator wiring functionally - EXT:products itself ships
 * no real implementation, this is fixture-only and never loaded outside functional tests.
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
