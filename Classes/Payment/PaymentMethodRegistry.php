<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Event\PaymentMethodsCollectedEvent;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotFoundException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class PaymentMethodRegistry
{
    /**
     * @var array<string, PaymentMethodInterface>
     */
    private array $paymentMethods = [];

    /**
     * @param iterable<PaymentMethodInterface> $paymentMethods
     */
    public function __construct(
        #[TaggedIterator('products.payment_method')]
        iterable $paymentMethods,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        foreach ($paymentMethods as $paymentMethod) {
            $this->paymentMethods[$paymentMethod->getIdentifier()] = $paymentMethod;
        }
    }

    /**
     * Discovery phase: the methods that may be offered for this context, highest priority first.
     *
     * @return array<PaymentMethodInterface>
     */
    public function getAvailable(PaymentContext $context): array
    {
        $available = array_values(array_filter(
            $this->paymentMethods,
            static fn(PaymentMethodInterface $method): bool => $method->isAvailable($context)
        ));

        usort(
            $available,
            static fn(PaymentMethodInterface $a, PaymentMethodInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );

        $event = new PaymentMethodsCollectedEvent($context, $available);
        $this->eventDispatcher->dispatch($event);

        return $event->getPaymentMethods();
    }

    public function get(string $identifier): PaymentMethodInterface
    {
        return $this->paymentMethods[$identifier] ?? throw new PaymentMethodNotFoundException(
            sprintf('Payment method "%s" is not registered.', $identifier),
            1751751010
        );
    }
}
