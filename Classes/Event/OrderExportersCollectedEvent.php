<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Export\OrderExportInterface;

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
