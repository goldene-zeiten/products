<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

/**
 * Cancel and refund are backend operations and receive an {@see OrderData} snapshot of the order.
 */
interface RefundablePaymentMethodInterface
{
    public function cancel(OrderData $order): PaymentResult;

    public function refund(OrderData $order, Money $amount): PaymentResult;
}
