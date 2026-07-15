<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration;

/**
 * Builds the PayPal Orders v2 "create order" payload from the shop order and payment context: a single
 * purchase unit for the order total, captured immediately, with the return/cancel URLs the customer is
 * sent back to after approving.
 */
final class PaypalOrderRequestBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(Order $order, PaymentContext $context, PaypalConfiguration $configuration): array
    {
        return [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => $order->getOrderNumber(),
                    'custom_id' => (string)($order->getUid() ?? 0),
                    'amount' => [
                        'currency_code' => $context->getCurrency(),
                        'value' => $context->getAmount()->getDecimalString(),
                    ],
                ],
            ],
            'payment_source' => [
                'paypal' => [
                    'experience_context' => $this->experienceContext($context, $configuration),
                ],
            ],
        ];
    }

    /**
     * The customer-facing return/cancel URLs are included only when a checkout page is configured (they are
     * empty otherwise); PayPal then falls back to the account's own defaults.
     *
     * @return array<string, string>
     */
    private function experienceContext(PaymentContext $context, PaypalConfiguration $configuration): array
    {
        return array_filter(
            [
                'brand_name' => $configuration->brandName,
                'user_action' => 'PAY_NOW',
                'return_url' => $context->getReturnUrl(),
                'cancel_url' => $context->getCancelUrl(),
            ],
            static fn(string $value): bool => $value !== '',
        );
    }
}
