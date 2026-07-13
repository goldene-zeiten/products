<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Export\OrderExportInterface;

/**
 * Lets integrators add or filter order exporters — inject custom exporters for SAP, analytics
 * platforms, or fulfillment partners. Mutable via {@see setExporters()}, which replaces the
 * exporter list before the export registry is finalized.
 *
 * {@see \GoldeneZeiten\Products\Export\OrderExportRegistry::getAvailable()}
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
