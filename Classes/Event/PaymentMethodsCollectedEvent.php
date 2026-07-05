<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;

final class PaymentMethodsCollectedEvent
{
    /**
     * @param array<PaymentMethodInterface> $paymentMethods
     */
    public function __construct(
        private readonly PaymentContext $context,
        private array $paymentMethods
    ) {}

    public function getContext(): PaymentContext
    {
        return $this->context;
    }

    /**
     * @return array<PaymentMethodInterface>
     */
    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    /**
     * @param array<PaymentMethodInterface> $paymentMethods
     */
    public function setPaymentMethods(array $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }
}
