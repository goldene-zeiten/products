:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

=============================
Developer / Extension Points
=============================

This extension has one public extension point: a PSR-14 event that lets an integrator adjust the
outgoing Stripe Checkout Session request before it is sent.

..  contents:: Table of contents
    :local:

..  _developer-modify-session-request:

ModifyStripeSessionRequestEvent
==================================

**Location:** :php:`GoldeneZeiten\Products\Payment\Stripe\Event\ModifyStripeSessionRequestEvent`

Fired by :php:`StripePaymentMethod` just before a Checkout Session is created via
`checkout.sessions.create() <https://docs.stripe.com/api/checkout/sessions/create>`__. The payload is
the plain associative array passed straight to the Stripe SDK — the same shape
:php:`StripePaymentMethod::buildSessionParameters()` produces as a minimal single-line-item
:code:`mode: payment` session for the order total. Typical reasons to listen:

*   Itemise the basket into several `line_items` instead of a single lump-sum line, so the shopper sees
    individual line items on Stripe's checkout page.
*   Attach `metadata` (Stripe deduplicates nothing by it, but it is the standard place to stash your own
    reconciliation keys) or set `locale`.
*   Restrict or extend `payment_method_types` (Checkout auto-selects eligible methods for the account and
    domain by default; setting this explicitly opts out of that automatic behaviour for the listed types).

Mutable: Yes (via `setPayload(array $payload)`)

**Methods:**

:php:`getPayload(): array`
    The create-session payload as built so far.

:php:`setPayload(array $payload): void`
    Replaces the payload entirely. A listener that only wants to add a field should read
    `getPayload()`, merge its own key(s) in, and pass the result back here.

:php:`getOrder(): GoldeneZeiten\Products\Core\Domain\Model\Order`
    The shop order the Checkout Session is being created for.

:php:`getContext(): GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext`
    The payment context passed to `initiate()` — amount, currency, country, and the return/cancel URLs
    the base payload's `success_url` / `cancel_url` are built from.

:php:`getConfiguration(): GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfiguration`
    The resolved Stripe configuration for the current site (see :ref:`configuration`) — useful when a
    listener's behaviour should itself depend on the configured secret key's environment or the API base
    URL.

Example: itemising the line items and attaching order metadata
==================================================================

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Payment\Stripe\Event\ModifyStripeSessionRequestEvent;
    use Symfony\Component\DependencyInjection\Attribute\AsEventListener;

    #[AsEventListener]
    final class ItemiseStripeSessionRequest
    {
        public function __invoke(ModifyStripeSessionRequestEvent $event): void
        {
            $payload = $event->getPayload();
            $order = $event->getOrder();
            $currency = strtolower($event->getContext()->getCurrency());

            $payload['line_items'] = array_map(
                static fn(array $item): array => [
                    'quantity' => $item['quantity'],
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => $item['unitAmountInCents'],
                        'product_data' => [
                            'name' => $item['title'],
                        ],
                    ],
                ],
                $this->basketItemsFor($order),
            );
            $payload['metadata'] = [
                'order_number' => $order->getOrderNumber(),
            ];

            $event->setPayload($payload);
        }

        /**
         * @return array<int, array{title: string, quantity: int, unitAmountInCents: int}>
         */
        private function basketItemsFor(\GoldeneZeiten\Products\Core\Domain\Model\Order $order): array
        {
            // Map the order's own line items to the shape used above.
            return [];
        }
    }

..  _developer-future-refunds:

Refunds and cancellations: not yet supported
================================================

This release implements only the pay flow (`RedirectPaymentMethodInterface`); it does not implement the
core `RefundablePaymentMethodInterface`, so the backend order module offers no refund/cancel action for a
Stripe-paid order. Adding it is a planned later phase, once it can call Stripe's
`Refunds <https://docs.stripe.com/api/refunds/create>`__ API the same way `StripePaymentMethod` already
calls `checkout.sessions.create()`/`retrieve()` — until then, refunding a Stripe payment is done directly
in the Stripe Dashboard.
