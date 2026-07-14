<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Export;

use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportResult;
use GoldeneZeiten\Products\Core\Export\Exception\OrderExporterNotAvailableException;

/**
 * Serves order exporters to their consumers: the discovery phase, listing what may be offered for an
 * order, and the execution phase, running the exporter that was selected.
 */
final class OrderExportService
{
    public function __construct(
        private readonly OrderExportRegistry $registry
    ) {}

    /**
     * @return array<OrderExportInterface>
     */
    public function availableFor(ExportContext $context): array
    {
        return $this->registry->getAvailable($context);
    }

    /**
     * Re-checks availability before exporting: an exporter that denied itself during discovery must not
     * become reachable by guessing its identifier.
     */
    public function export(string $identifier, ExportContext $context): ExportResult
    {
        $exporter = $this->registry->get($identifier);
        if (!$exporter->isAvailable($context)) {
            throw new OrderExporterNotAvailableException(
                sprintf('Order exporter "%s" is not available for this order.', $identifier),
                1784073601
            );
        }

        return new ExportResult(
            $exporter->export($context->getOrder()),
            $exporter->getContentType(),
            sprintf('%s.%s', $context->getOrder()->getOrderNumber(), $exporter->getFileExtension())
        );
    }
}
