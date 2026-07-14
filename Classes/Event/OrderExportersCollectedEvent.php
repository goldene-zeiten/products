<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Export\OrderExportInterface;
use GoldeneZeiten\Products\Export\OrderExportRegistry;

/**
 * Lets integrators reorder or filter the order exporters the backend is about to offer — hide an ERP
 * export from certain editors, or push a fulfillment export to the top of the list. Registering an
 * exporter is done by implementing {@see OrderExportInterface}; this event only post-filters the list
 * the registry already collected. Mutable via {@see OrderExportersCollectedEvent::setExporters()}.
 *
 * @see OrderExportRegistry::getAvailable()
 */
final class OrderExportersCollectedEvent
{
    /**
     * @param array<OrderExportInterface> $exporters
     */
    public function __construct(
        private readonly ExportContext $context,
        private array $exporters
    ) {}

    public function getContext(): ExportContext
    {
        return $this->context;
    }

    /**
     * @return array<OrderExportInterface>
     */
    public function getExporters(): array
    {
        return $this->exporters;
    }

    /**
     * @param array<OrderExportInterface> $exporters
     */
    public function setExporters(array $exporters): void
    {
        $this->exporters = $exporters;
    }
}
