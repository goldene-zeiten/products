:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

=============================
Developer / Extension Points
=============================

This extension has one public extension point: a PSR-14 event that lets an integrator adjust the
outgoing PayPal "create order" request before it is sent.

..  contents:: Table of contents
    :local:

..  _developer-modify-order-request:

ModifyPaypalOrderRequestEvent
================================

**Location:** :php:`GoldeneZeiten\Products\Payment\Paypal\Event\ModifyPaypalOrderRequestEvent`

Fired by :php:`HttpPaypalOrderClient` just before a `create order` request is sent to PayPal's
`Orders v2 <https://developer.paypal.com/docs/api/orders/v2/>`__ API. The payload is the plain
associative array that gets JSON-serialised as the request body — the same shape
:php:`PaypalOrderRequestBuilder` produces as a minimal single-purchase-unit order for the order
total. Typical reasons to listen:

*   Itemise the basket into `purchase_units[0].items[]` instead of a single lump-sum amount, so the
    shopper sees individual line items on the PayPal approval page.
*   Add an `invoice_id` (PayPal deduplicates by this field, so it is a good idempotency anchor
    beyond the order id) or a `soft_descriptor` for the shopper's card/PayPal statement.
*   Add a shipping address, or switch `payment_source.paypal.experience_context` fields PayPal
    supports (e.g. `landing_page`, `shipping_preference`) that this extension does not set itself.

Mutable: Yes (via `setPayload(array $payload)`)

**Methods:**

:php:`getPayload(): array`
    The create-order payload as built so far.

:php:`setPayload(array $payload): void`
    Replaces the payload entirely. A listener that only wants to add a field should read
    `getPayload()`, merge its own key(s) in, and pass the result back here.

:php:`getOrder(): GoldeneZeiten\Products\Core\Domain\Model\Order`
    The shop order the PayPal order is being created for.

:php:`getContext(): GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext`
    The payment context passed to `initiate()` — amount, currency, country, and the return/cancel
    URLs the base payload's `experience_context` is built from.

:php:`getConfiguration(): GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration`
    The resolved PayPal configuration for the current site (see :ref:`configuration`) — useful when
    a listener's behaviour should itself depend on the configured environment or brand name.

Example: itemising the purchase unit and adding an invoice id
=================================================================

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Payment\Paypal\Event\ModifyPaypalOrderRequestEvent;
    use Symfony\Component\DependencyInjection\Attribute\AsEventListener;

    #[AsEventListener]
    final class ItemisePaypalOrderRequest
    {
        public function __invoke(ModifyPaypalOrderRequestEvent $event): void
        {
            $payload = $event->getPayload();
            $order = $event->getOrder();

            $payload['purchase_units'][0]['invoice_id'] = $order->getOrderNumber();
            $payload['purchase_units'][0]['items'] = array_map(
                static fn(array $item): array => [
                    'name' => $item['title'],
                    'quantity' => (string)$item['quantity'],
                    'unit_amount' => [
                        'currency_code' => $event->getContext()->getCurrency(),
                        'value' => $item['unitPrice'],
                    ],
                ],
                $this->basketItemsFor($order),
            );

            $event->setPayload($payload);
        }

        /**
         * @return array<int, array{title: string, quantity: int, unitPrice: string}>
         */
        private function basketItemsFor(\GoldeneZeiten\Products\Core\Domain\Model\Order $order): array
        {
            // Map the order's own line items to the shape used above.
            return [];
        }
    }

Adding `items[]` to a purchase unit without also making PayPal's `amount.value` and
`amount.breakdown.item_total` match the summed item amounts is rejected by PayPal — if you itemise,
also set `amount.breakdown` accordingly.

..  _developer-future-refunds:

Refunds and cancellations: not yet supported
================================================

This release implements only the pay flow (`RedirectPaymentMethodInterface`); it does not implement
the core `RefundablePaymentMethodInterface`, so the backend order module offers no refund/cancel
action for a PayPal-paid order. Adding it is a planned later phase, once it can call PayPal's
`Captures <https://developer.paypal.com/docs/api/payments/v2/#captures_refund>`__ refund API the
same way `HttpPaypalOrderClient` already calls create/capture — until then, refunding a PayPal
payment is done directly in the PayPal account.
