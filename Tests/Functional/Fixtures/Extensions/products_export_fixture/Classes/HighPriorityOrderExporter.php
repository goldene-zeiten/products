<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ExportFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Export\OrderExportInterface;

/**
 * Proves priority sorting — this exporter is offered before the dummy exporter.
 */
final class HighPriorityOrderExporter implements OrderExportInterface
{
    public function getIdentifier(): string
    {
        return 'high-priority';
    }

    public function getLabel(): string
    {
        return 'High Priority Export';
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
        return true;
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function export(Order $order): string
    {
        return sprintf('high-priority:%s', $order->getOrderNumber());
    }
}
