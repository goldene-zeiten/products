:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

=============================
Developer / Extension Points
=============================

This extension has one public extension point: a PSR-14 event that lets an integrator adjust the
outgoing Klarna Payments "create session" request before it is sent.

..  contents:: Table of contents
    :local:

..  _developer-modify-session-request:

ModifyKlarnaSessionRequestEvent
==================================

**Location:** :php:`GoldeneZeiten\Products\Payment\Klarna\Event\ModifyKlarnaSessionRequestEvent`

Fired by :php:`KlarnaPaymentMethod` just before a Klarna Payments session is created
(:code:`POST /payments/v1/sessions`). The payload is the plain associative array that gets
JSON-serialised as the request body — the same shape
:php:`GoldeneZeiten\Products\Payment\Klarna\Order\KlarnaOrderPayloadBuilder` produces as a single order
line for the order total, plus :code:`intent: 'buy'`. Typical reasons to listen:

*   Itemise the basket into several `order_lines[]` instead of a single lump-sum line, so the shopper
    sees individual products on Klarna's Hosted Payment Page.
*   Set a non-zero `order_tax_amount` and per-line `tax_rate`/`total_tax_amount` for a shop that
    calculates VAT separately from the order total.
*   Set a specific `locale` (the builder defaults to `en-<country>`) or attach `merchant_data` — free-
    form JSON Klarna returns unchanged on every later read of the session/order, useful for correlating
    with the shop's own systems.

Mutable: Yes (via `setPayload(array $payload)`)

**Methods:**

:php:`getPayload(): array`
    The create-session payload as built so far.

:php:`setPayload(array $payload): void`
    Replaces the payload entirely. A listener that only wants to add a field should read
    `getPayload()`, merge its own key(s) in, and pass the result back here.

:php:`getOrder(): GoldeneZeiten\Products\Core\Domain\Model\Order`
    The shop order the Klarna session is being opened for.

:php:`getContext(): GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext`
    The payment context passed to `initiate()` — amount, currency, country, and the return/cancel/
    webhook URLs the Hosted Payment Page session's `merchant_urls` are built from.

:php:`getConfiguration(): GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfiguration`
    The resolved Klarna configuration for the current site (see :ref:`configuration`) — useful when a
    listener's behaviour should itself depend on the configured environment.

Example: attaching merchant data and a specific locale
==========================================================

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Payment\Klarna\Event\ModifyKlarnaSessionRequestEvent;
    use Symfony\Component\DependencyInjection\Attribute\AsEventListener;

    #[AsEventListener]
    final class AttachMerchantDataToKlarnaSession
    {
        public function __invoke(ModifyKlarnaSessionRequestEvent $event): void
        {
            $payload = $event->getPayload();
            $order = $event->getOrder();

            $payload['locale'] = 'de-DE';
            $payload['merchant_data'] = json_encode(
                [
                    'shop_order_number' => $order->getOrderNumber(),
                    'sales_channel' => 'web',
                ],
                JSON_THROW_ON_ERROR,
            );

            $event->setPayload($payload);
        }
    }

..  warning::

    The event only affects the **session** request. The later order-placement call
    (:code:`POST /payments/v1/authorizations/{token}/order`) is always built fresh from
    :php:`KlarnaOrderPayloadBuilder` as a single order line for the order total — it does not replay
    whatever the event changed. If a listener itemises `order_lines` here, keep `order_amount` (and, for
    an itemised cart, the summed line totals) consistent with what the order-placement call will send;
    Klarna rejects placing an order whose cart does not match the session it was opened for. This is why
    the shipped payload deliberately stays a single order line for the whole total (see
    :ref:`introduction`) — it keeps the two calls in lock step without an integrator having to duplicate
    the itemisation logic on both sides.

..  _developer-future-refunds:

Refunds and cancellations: not yet supported
================================================

This release implements only the pay flow (`RedirectPaymentMethodInterface`); it does not implement the
core `RefundablePaymentMethodInterface`, so the backend order module offers no refund/cancel action for
a Klarna-paid order. Adding it is a planned later phase, once it can call Klarna's order management/
refund API the same way :php:`HttpKlarnaClient` already calls session-open/order-place — until then,
refunding a Klarna payment is done directly in the Klarna Merchant Portal.
