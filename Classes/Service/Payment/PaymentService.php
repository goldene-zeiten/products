<?php
declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Payment;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class PaymentService
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
        iterable $paymentMethods
    ) {
        foreach ($paymentMethods as $paymentMethod) {
            $this->paymentMethods[$paymentMethod->getIdentifier()] = $paymentMethod;
        }
    }

    /**
     * @return array<PaymentMethodInterface>
     */
    public function getAvailablePaymentMethods(): array
    {
        return array_values($this->paymentMethods);
    }

    public function getPaymentMethod(string $identifier): ?PaymentMethodInterface
    {
        return $this->paymentMethods[$identifier] ?? null;
    }
}
