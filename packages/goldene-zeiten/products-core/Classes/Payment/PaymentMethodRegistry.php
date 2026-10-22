<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Event\PaymentMethodsCollectedEvent;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotAvailableException;
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
     * Every registered method, whether or not it would be offered for a given basket - for documentation of
     * what the shop supports, not for offering a choice to a customer.
     *
     * @return array<PaymentMethodInterface>
     */
    public function getAll(): array
    {
        return array_values($this->paymentMethods);
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

    /**
     * Execution phase: the chosen method, but only if {@see PaymentMethodRegistry::getAvailable()} would offer
     * it for this context - a stored or submitted identifier is never trusted on its own.
     *
     * @throws PaymentMethodNotAvailableException
     */
    public function getAvailableMethod(string $identifier, PaymentContext $context): PaymentMethodInterface
    {
        foreach ($this->getAvailable($context) as $paymentMethod) {
            if ($paymentMethod->getIdentifier() === $identifier) {
                return $paymentMethod;
            }
        }

        throw new PaymentMethodNotAvailableException(
            sprintf('Payment method "%s" is not available for this basket.', $identifier),
            1791662739
        );
    }

    public function get(string $identifier): PaymentMethodInterface
    {
        return $this->paymentMethods[$identifier] ?? throw new PaymentMethodNotFoundException(
            sprintf('Payment method "%s" is not registered.', $identifier),
            1751751010
        );
    }
}
