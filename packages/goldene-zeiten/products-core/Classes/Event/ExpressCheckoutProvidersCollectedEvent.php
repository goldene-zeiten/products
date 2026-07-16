<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;

/**
 * Lets integrators add, filter or reorder the express-checkout buttons shown on the cart or product page -
 * restrict a wallet by region or basket value, or inject a custom provider. Mutable via
 * {@see ExpressCheckoutProvidersCollectedEvent::setProviders()} before the buttons are rendered.
 *
 * @see ExpressCheckoutProviderRegistry::getAvailable()
 */
final class ExpressCheckoutProvidersCollectedEvent
{
    /**
     * @param array<ExpressCheckoutProviderInterface> $providers
     */
    public function __construct(
        private readonly ExpressCheckoutContext $context,
        private array $providers
    ) {}

    public function getContext(): ExpressCheckoutContext
    {
        return $this->context;
    }

    /**
     * @return array<ExpressCheckoutProviderInterface>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * @param array<ExpressCheckoutProviderInterface> $providers
     */
    public function setProviders(array $providers): void
    {
        $this->providers = $providers;
    }
}
