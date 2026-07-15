<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Export;

use GoldeneZeiten\Products\Core\Domain\Dto\Export\ExportContext;
use GoldeneZeiten\Products\Core\Event\OrderExportersCollectedEvent;
use GoldeneZeiten\Products\Core\Export\Exception\OrderExporterNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Serves the order exporters registered by integrators: discovery, for the backend to offer a choice,
 * and resolution by identifier, for executing the one an editor selected.
 *
 * Public, because the backend instantiates it through makeInstance to discover available exporters.
 */
#[Autoconfigure(public: true)]
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
     * Discovery phase: the exporters that may be offered for this context, highest priority first.
     *
     * @return array<OrderExportInterface>
     */
    public function getAvailable(ExportContext $context): array
    {
        $available = array_values(array_filter(
            $this->exporters,
            static fn(OrderExportInterface $exporter): bool => $exporter->isAvailable($context)
        ));

        usort(
            $available,
            static fn(OrderExportInterface $a, OrderExportInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );

        $event = new OrderExportersCollectedEvent($context, $available);
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
