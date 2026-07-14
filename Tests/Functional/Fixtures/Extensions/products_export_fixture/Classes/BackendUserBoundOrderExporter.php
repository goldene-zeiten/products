<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ExportFixture;

use GoldeneZeiten\Products\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Export\OrderExportInterface;

/**
 * Proves the context actually reaches the exporter with real data — available only for backend user 1.
 */
final class BackendUserBoundOrderExporter implements OrderExportInterface
{
    public function getIdentifier(): string
    {
        return 'be-user-bound';
    }

    public function getLabel(): string
    {
        return 'Backend User Bound Export';
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
        return $context->getBackendUserUid() === 1;
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function export(Order $order): string
    {
        return sprintf('be-user-bound:%s', $order->getOrderNumber());
    }
}
