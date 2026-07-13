<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Export\OrderExportInterface;
use GoldeneZeiten\Products\Export\OrderExportRegistry;

/**
 * Lets integrators add or filter order exporters — inject custom exporters for SAP, analytics
 * platforms, or fulfillment partners. Mutable via {@see OrderExportersCollectedEvent::setExporters()}, which replaces the
 * exporter list before the export registry is finalized.
 *
 * @see OrderExportRegistry::getAvailable()
 */
final class OrderExportersCollectedEvent
{
    /**
     * @param array<OrderExportInterface> $exporters
     */
    public function __construct(
        private array $exporters
    ) {}

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
