<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\ValueObject\Money;

interface RefundablePaymentMethodInterface
{
    public function cancel(Order $order): PaymentResult;

    public function refund(Order $order, Money $amount): PaymentResult;
}
