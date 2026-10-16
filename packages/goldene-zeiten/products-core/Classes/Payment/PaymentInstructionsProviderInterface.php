<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Model\Order;

/**
 * Implemented by a payment method whose customer still has to act after placing the order, e.g. transfer the
 * amount for prepayment. The order mails show the returned plain text; an empty string shows nothing.
 */
interface PaymentInstructionsProviderInterface
{
    public function getPaymentInstructions(Order $order): string;
}
