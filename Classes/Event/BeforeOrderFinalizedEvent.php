<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;

final class BeforeOrderFinalizedEvent
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
