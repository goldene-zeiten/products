<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Export;

use GoldeneZeiten\Products\Event\OrderExportersCollectedEvent;
use GoldeneZeiten\Products\Export\Exception\OrderExporterNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class OrderExportRegistry
{
    /**
     * @var array<string, OrderExportInterface>
     */
    private array $exporters = [];

    /**
     * @param iterable<OrderExportInterface> $exporters
     */
    public function __construct(
        #[TaggedIterator('products.order_export')]
        iterable $exporters,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        foreach ($exporters as $exporter) {
            $this->exporters[$exporter->getIdentifier()] = $exporter;
        }
    }

    /**
     * @return array<OrderExportInterface>
     */
    public function getAvailable(): array
    {
        $event = new OrderExportersCollectedEvent(array_values($this->exporters));
        $this->eventDispatcher->dispatch($event);

        return $event->getExporters();
    }

    public function get(string $identifier): OrderExportInterface
    {
        return $this->exporters[$identifier] ?? throw new OrderExporterNotFoundException(
            sprintf('Order exporter "%s" is not registered.', $identifier),
            1783900000
        );
    }
}
