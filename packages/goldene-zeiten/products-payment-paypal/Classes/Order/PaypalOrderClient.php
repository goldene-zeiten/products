<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration;
use GoldeneZeiten\Products\Payment\Paypal\Domain\Dto\PaypalCapture;
use GoldeneZeiten\Products\Payment\Paypal\Domain\Dto\PaypalOrder;
use GoldeneZeiten\Products\Payment\Paypal\Exception\PaypalApiException;

/**
 * Talks to the PayPal Orders v2 API: create the order the customer approves, and capture the money once
 * they have. Split behind an interface so the payment method can be tested against a fake without HTTP.
 */
interface PaypalOrderClient
{
    /**
     * @throws PaypalApiException
     */
    public function createOrder(Order $order, PaymentContext $context, PaypalConfiguration $configuration): PaypalOrder;

    /**
     * @throws PaypalApiException
     */
    public function capture(string $paypalOrderId, PaypalConfiguration $configuration): PaypalCapture;
}
