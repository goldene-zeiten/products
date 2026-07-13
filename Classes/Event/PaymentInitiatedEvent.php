<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;

/**
 * Notifies integrators when payment processing begins — submit payment details to a gateway,
 * record the transaction, or trigger additional validation. Fired after order creation but
 * before the customer is redirected to payment or finalization.
 *
 * @see OrderPlacementService::place()
 */
final class PaymentInitiatedEvent
{
    public function __construct(
        private readonly Order $order,
        private readonly PaymentResult $paymentResult
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getPaymentResult(): PaymentResult
    {
        return $this->paymentResult;
    }
}
