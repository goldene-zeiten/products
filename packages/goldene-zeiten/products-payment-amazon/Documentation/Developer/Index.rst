:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

=============================
Developer / Extension Points
=============================

This extension has one public extension point: a PSR-14 event that lets an integrator adjust the
outgoing Amazon Checkout Session "create session" request before it is sent.

..  contents:: Table of contents
    :local:

..  _developer-modify-session-request:

ModifyAmazonCheckoutSessionRequestEvent
========================================

**Location:** :php:`GoldeneZeiten\Products\Payment\Amazon\Event\ModifyAmazonCheckoutSessionRequestEvent`

Fired by :php:`AmazonPayPaymentMethod` just before an Amazon Checkout Session is created
(:code:`POST /checkout/sessions`). The payload is the plain associative array that gets
JSON-serialised as the request body — the same shape the extension builds from the order,
region, and configuration. Typical reasons to listen:

*   Set specific merchant metadata (the :code:`merchantMetadata` object) for tracking or reporting
    purposes.
*   Adjust delivery specifications (shipping addresses, methods) if the shop's model differs from Amazon's
    assumptions.
*   Set a non-standard locale if the shop supports regions outside Amazon's defaults.
*   Override or add custom fields that Amazon Pay accepts in the session payload.

Mutable: Yes (via `setPayload(array $payload)`)

**Methods:**

:php:`getPayload(): array`
    The create-session payload as built so far.

:php:`setPayload(array $payload): void`
    Replaces the payload entirely. A listener that only wants to add a field should read
    `getPayload()`, merge its own key(s) in, and pass the result back here.

:php:`getOrder(): GoldeneZeiten\Products\Core\Domain\Model\Order`
    The shop order the Amazon session is being opened for.

:php:`getContext(): GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext`
    The payment context passed to `initiate()` — amount, currency, country, and the return/cancel/
    webhook URLs the session's `redirectConfiguration.returnUrl` is built from.

:php:`getConfiguration(): GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfiguration`
    The resolved Amazon configuration for the current site (see :ref:`configuration`) — useful when a
    listener's behaviour should itself depend on the configured region or environment.

Example: attaching merchant metadata
=====================================

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Payment\Amazon\Event\ModifyAmazonCheckoutSessionRequestEvent;
    use Symfony\Component\DependencyInjection\Attribute\AsEventListener;

    #[AsEventListener]
    final class AttachMerchantDataToAmazonSession
    {
        public function __invoke(ModifyAmazonCheckoutSessionRequestEvent $event): void
        {
            $payload = $event->getPayload();
            $order = $event->getOrder();

            $payload['merchantMetadata'] = [
                'orderNumber' => $order->getOrderNumber(),
                'salesChannel' => 'web',
                'customValue' => 'example',
            ];

            $event->setPayload($payload);
        }
    }

..  _developer-future-refunds:

Refunds and cancellations: not yet supported
=============================================

This release implements only the pay flow (`RedirectPaymentMethodInterface`); it does not implement the
core `RefundablePaymentMethodInterface`, so the backend order module offers no refund/cancel action for
an Amazon-paid order. Adding it is a planned later phase, once it can call Amazon's refund API the
same way :php:`HttpAmazonPayClient` already calls checkout-session-create/update/complete — until then,
refunding an Amazon payment is done directly in Seller Central.
