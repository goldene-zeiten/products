<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\Order\OrderFinalizationService;

/**
 * Notifies integrators just before an order transitions to finalized — a last chance to verify
 * inventory, apply additional discounts, or reject the order. The order is not yet persisted
 * when this fires, but payment has been initiated.
 *
 * @see OrderFinalizationService::finalize()
 */
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
