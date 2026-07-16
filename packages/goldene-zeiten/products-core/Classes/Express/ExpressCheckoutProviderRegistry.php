<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Event\ExpressCheckoutProvidersCollectedEvent;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressCheckoutProviderNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Discovers the express-checkout providers registered via the {@see ExpressCheckoutProviderInterface} tag,
 * mirroring {@see PaymentMethodRegistry}: the cart/product page asks for the ones available for the current
 * basket, and a callback resolves a provider back from the identifier its button sent.
 */
#[Autoconfigure(public: true)]
final class ExpressCheckoutProviderRegistry
{
    /**
     * @var array<string, ExpressCheckoutProviderInterface>
     */
    private array $providers = [];

    /**
     * @param iterable<ExpressCheckoutProviderInterface> $providers
     */
    public function __construct(
        #[TaggedIterator('products.express_checkout_provider')]
        iterable $providers,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        foreach ($providers as $provider) {
            $this->providers[$provider->getIdentifier()] = $provider;
        }
    }

    /**
     * The providers whose button may be shown for this basket, highest priority first.
     *
     * @return array<ExpressCheckoutProviderInterface>
     */
    public function getAvailable(ExpressCheckoutContext $context): array
    {
        $available = array_values(array_filter(
            $this->providers,
            static fn(ExpressCheckoutProviderInterface $provider): bool => $provider->isAvailable($context)
        ));

        usort(
            $available,
            static fn(ExpressCheckoutProviderInterface $a, ExpressCheckoutProviderInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );

        $event = new ExpressCheckoutProvidersCollectedEvent($context, $available);
        $this->eventDispatcher->dispatch($event);

        return $event->getProviders();
    }

    public function get(string $identifier): ExpressCheckoutProviderInterface
    {
        return $this->providers[$identifier] ?? throw new ExpressCheckoutProviderNotFoundException(
            sprintf('Express checkout provider "%s" is not registered.', $identifier),
            1784220767
        );
    }
}
