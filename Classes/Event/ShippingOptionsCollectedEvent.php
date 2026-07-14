<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Shipping\ShippingProviderInterface;
use GoldeneZeiten\Products\Shipping\ShippingProviderRegistry;

/**
 * Lets integrators reorder or hide the shipping options the checkout is about to offer - promote a
 * pickup point, or drop express shipping for wholesale customers. Registering a carrier is done by
 * implementing {@see ShippingProviderInterface}; this event only post-filters the options the carriers
 * already offered. Mutable via {@see ShippingOptionsCollectedEvent::setOptions()}.
 *
 * @see ShippingProviderRegistry::getAvailableOptions()
 */
final class ShippingOptionsCollectedEvent
{
    /**
     * @param ShippingOption[] $options
     */
    public function __construct(
        private readonly ShippingContext $context,
        private array $options
    ) {}

    public function getContext(): ShippingContext
    {
        return $this->context;
    }

    /**
     * @return ShippingOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param ShippingOption[] $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}
