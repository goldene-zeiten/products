<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;

/**
 * Fired after the DHL rates have been mapped to shipping options, so an integrator can drop, reorder,
 * relabel or surcharge the options before they reach the basket.
 */
final class ModifyDhlExpressShippingOptionsEvent
{
    /**
     * @param ShippingOption[] $options
     */
    public function __construct(
        private array $options,
        private readonly ShippingContext $context,
        private readonly DhlExpressConfiguration $configuration,
    ) {}

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

    public function getContext(): ShippingContext
    {
        return $this->context;
    }

    public function getConfiguration(): DhlExpressConfiguration
    {
        return $this->configuration;
    }
}
