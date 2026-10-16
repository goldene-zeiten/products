<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotFoundException;

/**
 * What the customer still has to do to pay an order, as told by its payment method
 * ({@see PaymentInstructionsProviderInterface}); empty when there is nothing to do or the method is gone.
 */
final class PaymentInstructionsResolver
{
    public function __construct(
        private readonly PaymentMethodRegistry $paymentMethodRegistry
    ) {}

    public function resolve(Order $order): string
    {
        try {
            $paymentMethod = $this->paymentMethodRegistry->get($order->getPaymentMethod());
        } catch (PaymentMethodNotFoundException) {
            return '';
        }
        return $paymentMethod instanceof PaymentInstructionsProviderInterface ? $paymentMethod->getPaymentInstructions($order) : '';
    }
}
