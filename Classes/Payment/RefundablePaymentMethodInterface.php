<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

interface RefundablePaymentMethodInterface
{
    public function cancel(Order $order): PaymentResult;

    public function refund(Order $order, Money $amount): PaymentResult;
}
