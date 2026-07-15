<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;

/**
 * Fired just before a rate request is sent to DHL Express, so an integrator can adjust the outgoing query
 * parameters - change the package dimensions, force `isCustomsDeclarable`, request a specific product, and
 * so on. The parameters are the associative array serialised to the DHL `GET /rates` query string.
 */
final class ModifyDhlExpressRateRequestEvent
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        private array $parameters,
        private readonly ShippingContext $context,
        private readonly DhlExpressConfiguration $configuration,
    ) {}

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
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
