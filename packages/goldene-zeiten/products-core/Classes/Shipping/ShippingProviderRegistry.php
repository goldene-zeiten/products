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
 *
 * A {@see FallbackShippingProviderInterface} carrier (the shop's own manual shipping) is only offered when
 * no real carrier can serve the basket, so a carrier extension takes over automatically once installed and
 * the manual shipping fills back in for anything that carrier refuses. A
 * {@see SupplementaryShippingProviderInterface} provider, such as collecting in store, is offered next to
 * either of them.
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
     * The identifiers of all registered carriers, highest priority first - for documentation of what the shop
     * supports, since the options themselves depend on the basket and the destination.
     *
     * @return string[]
     */
    public function getIdentifiers(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Discovery phase: every option a real carrier can serve for this basket, or - only when none can - the
     * options of the shop's own fallback carrier; the supplementary options follow either.
     *
     * @return ShippingOption[]
     */
    public function getAvailableOptions(ShippingContext $context): array
    {
        $options = $this->quoteFrom($this->providersOfKind(fallback: false), $context);
        if ($options === []) {
            $options = $this->quoteFrom($this->providersOfKind(fallback: true), $context);
        }
        $options = [...$options, ...$this->quoteFrom($this->supplementaryProviders(), $context)];

        $event = new ShippingOptionsCollectedEvent($context, $options);
        $this->eventDispatcher->dispatch($event);

        return $event->getOptions();
    }

    /**
     * @param ShippingProviderInterface[] $providers
     * @return ShippingOption[]
     */
    private function quoteFrom(array $providers, ShippingContext $context): array
    {
        $options = [];
        foreach ($providers as $provider) {
            foreach ($provider->quote($context) as $option) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * The registered carriers of one kind: the real carriers, or the fallback ones. Supplementary
     * providers are neither.
     *
     * @return ShippingProviderInterface[]
     */
    private function providersOfKind(bool $fallback): array
    {
        return array_filter(
            $this->providers,
            static fn(ShippingProviderInterface $provider): bool => !$provider instanceof SupplementaryShippingProviderInterface
                && ($provider instanceof FallbackShippingProviderInterface) === $fallback
        );
    }

    /**
     * @return ShippingProviderInterface[]
     */
    private function supplementaryProviders(): array
    {
        return array_filter(
            $this->providers,
            static fn(ShippingProviderInterface $provider): bool => $provider instanceof SupplementaryShippingProviderInterface
        );
    }

    /**
     * Execution phase: the option behind the key the customer's choice was stored under. Null when the
     * carrier is gone, or when the option is not among those {@see ShippingProviderRegistry::getAvailableOptions()}
     * offers for this basket - a stored key never bypasses the fallback rule or an integrator's filter.
     */
    public function resolveOption(string $key, ShippingContext $context): ?ShippingOption
    {
        [$providerIdentifier, $optionIdentifier] = ShippingOption::splitKey($key);
        $provider = $this->providers[$providerIdentifier] ?? null;
        if ($provider === null || $optionIdentifier === '' || !$this->isOffered($key, $context)) {
            return null;
        }

        return $provider->resolve($optionIdentifier, $context);
    }

    public function isOffered(string $key, ShippingContext $context): bool
    {
        foreach ($this->getAvailableOptions($context) as $option) {
            if ($option->getKey() === $key) {
                return true;
            }
        }

        return false;
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
