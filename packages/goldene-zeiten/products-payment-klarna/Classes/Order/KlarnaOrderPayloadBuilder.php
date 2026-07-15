<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Order;

/**
 * Builds the Klarna order payload (amounts in minor units + a single order line for the order total) used
 * both to open the payment session and to place the order afterwards. Keeping it in one place is what makes
 * the two payloads match - Klarna refuses to place an order whose cart differs from the session's.
 *
 * A single line for the whole order total is a deliberate simplification: it keeps the two calls in lock
 * step without itemising the basket, which an integrator can still do through the session event.
 */
final class KlarnaOrderPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function build(int $amountMinorUnits, string $currency, string $countryCode, string $orderNumber): array
    {
        return [
            'purchase_country' => strtoupper($countryCode),
            'purchase_currency' => strtoupper($currency),
            'locale' => 'en-' . strtoupper($countryCode),
            'order_amount' => $amountMinorUnits,
            'order_tax_amount' => 0,
            'order_lines' => [
                [
                    'type' => 'physical',
                    'reference' => $orderNumber,
                    'name' => 'Order ' . $orderNumber,
                    'quantity' => 1,
                    'unit_price' => $amountMinorUnits,
                    'tax_rate' => 0,
                    'total_amount' => $amountMinorUnits,
                    'total_tax_amount' => 0,
                ],
            ],
        ];
    }
}
