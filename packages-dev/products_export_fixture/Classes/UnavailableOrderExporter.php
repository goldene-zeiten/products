<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ExportFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Export\OrderExportInterface;

/**
 * Proves isAvailable() is honoured — this exporter is never offered.
 */
final class UnavailableOrderExporter implements OrderExportInterface
{
    public function getIdentifier(): string
    {
        return 'unavailable';
    }

    public function getLabel(): string
    {
        return 'Unavailable Export';
    }

    public function getContentType(): string
    {
        return 'text/plain';
    }

    public function getFileExtension(): string
    {
        return 'txt';
    }

    public function isAvailable(ExportContext $context): bool
    {
        return false;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function export(Order $order): string
    {
        return sprintf('unavailable:%s', $order->getOrderNumber());
    }
}
