<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Event\ShippingOptionsCollectedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Serves the registered carriers: every option they can offer for a basket, collected into the one list
 * the customer chooses from, and resolution of the option they chose.
 *
 * The customer never sees which carrier an option came from - only "DHL Express, 9,90" - so the options
 * of all carriers are pooled rather than grouped.
 */
final class ShippingProviderRegistry
{
    /**
     * @var array<string, ShippingProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<ShippingProviderInterface> $providers
     */
    public function __construct(
        #[TaggedIterator('products.shipping_provider')]
        iterable $providers,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        foreach ($this->sortByPriority([...$providers]) as $provider) {
            $this->providers[$provider->getIdentifier()] = $provider;
        }
    }

    /**
     * Discovery phase: every option every carrier can serve for this basket.
     *
     * @return ShippingOption[]
     */
    public function getAvailableOptions(ShippingContext $context): array
    {
        $options = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->quote($context) as $option) {
                $options[] = $option;
            }
        }

        $event = new ShippingOptionsCollectedEvent($context, $options);
        $this->eventDispatcher->dispatch($event);

        return $event->getOptions();
    }

    /**
     * Execution phase: the option behind the key the customer's choice was stored under. Null when the
     * carrier is gone, or when it no longer offers that option for this basket.
     */
    public function resolveOption(string $key, ShippingContext $context): ?ShippingOption
    {
        [$providerIdentifier, $optionIdentifier] = ShippingOption::splitKey($key);
        $provider = $this->providers[$providerIdentifier] ?? null;
        if ($provider === null || $optionIdentifier === '') {
            return null;
        }

        return $provider->resolve($optionIdentifier, $context);
    }

    /**
     * @param ShippingProviderInterface[] $providers
     * @return ShippingProviderInterface[]
     */
    private function sortByPriority(array $providers): array
    {
        usort(
            $providers,
            static fn(ShippingProviderInterface $a, ShippingProviderInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );

        return $providers;
    }
}
