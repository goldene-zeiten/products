:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_payment_klarna adds Klarna as a payment method to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop, via Klarna's Hosted Payment Page
(HPP). It plugs into the core shop's existing Payment Methods API
(:php:`GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface`, documented in the
``products-core`` extension's own :file:`Documentation/Developer/Api/PaymentMethods.rst`) — the same
seam :guilabel:`Invoice` and PayPal use — so it simply appears alongside them at checkout, without any
change to the shop's checkout flow.

..  contents:: Table of contents
    :local:

The payment flow
=================

:php:`GoldeneZeiten\Products\Payment\Klarna\Payment\KlarnaPaymentMethod` implements the core
:php:`RedirectPaymentMethodInterface`. Redirect payment methods have three moving parts, and this
extension implements all three:

1.  **`initiate()`** first opens a Klarna Payments session (:code:`POST /payments/v1/sessions`) for the
    order total, then wraps it in a Hosted Payment Page session
    (:code:`POST /hpp/v1/sessions`, with :code:`options.place_order_mode` set to :code:`NONE` so Klarna
    never places the order itself). It returns :php:`PaymentResult::redirectRequired()` with the HPP
    session's :code:`redirect_url`. The customer's browser is sent there to choose how to pay — pay now,
    pay later, or in instalments — and to authorize.

2.  **`handleReturn()`** runs when Klarna sends the customer's browser back to the shop's checkout.
    Klarna does this by substituting its own :code:`{{authorization_token}}` placeholder into the
    success URL this extension registered as :code:`merchant_urls.success` when creating the HPP session
    — so the returning request carries a genuine :code:`authorization_token` query parameter (not the
    shop's own signed callback token, which lives under the checkout plugin's own namespace — the two
    never collide). The method places the order **server-to-server** against that authorization token
    (:code:`POST /payments/v1/authorizations/{token}/order`) and interprets Klarna's fraud decision:
    :code:`ACCEPTED` marks the order paid, :code:`PENDING` leaves it pending, and :code:`REJECTED` fails
    it.

3.  **`handleWebhook()`** runs independently, whenever Klarna posts its asynchronous
    :code:`status_update` callback to the shop's fixed payment webhook middleware (registered by
    ``products-core`` at path :code:`/products/payment/webhook`). Klarna does not sign this callback, so
    before trusting it the extension re-reads the HPP session from Klarna itself (see
    :ref:`introduction-webhook-verification` below) — an unverified or forged notification is never
    allowed to mark an order paid.

Both callbacks are idempotent, as the core API requires: an order that is already
:php:`PaymentStatus::PAID` is not placed again on a replayed return or webhook — `placeOrder()` short-
circuits and returns the already-paid result without calling Klarna at all.

..  _introduction-webhook-verification:

Webhook verification without a signature
==========================================

Klarna's :code:`status_update` callback carries only a session id, with no signature to check. Instead
of verifying a signature, :php:`KlarnaPaymentMethod::finalizeFromWebhook()` re-reads the HPP session
straight from Klarna (:code:`GET /hpp/v1/sessions/{id}`), authenticated with the shop's own configured
Basic credentials. A forged callback body cannot make that authenticated call report a completed
session with a valid authorization token — this is the same "verify with the gateway, never trust the
caller" rule the core Payment Methods API requires of every redirect method. Only once the re-read
session reports :code:`status: COMPLETED` with a non-empty authorization token does the webhook go on
to place the order; anything else (still :code:`WAITING`, or a failed re-read) resolves to
:php:`PaymentResult::pending()` or :php:`PaymentResult::failed()` respectively, never to a paid order.

Availability at checkout
==========================

Klarna is only offered once it is actually usable: `isAvailable()` requires both a configured username
and password (see :ref:`configuration`) and an order currency Klarna settles in
(:php:`KlarnaPaymentMethod::SUPPORTED_CURRENCIES` — the euro-zone and the other major currencies Klarna
operates in). An unconfigured installation, or an order in an unsupported currency, therefore shows no
Klarna option at all, rather than offering a method that would fail as soon as the customer picked it.

Shared API infrastructure
===========================

Outbound HTTP calls go through the shared
`goldene-zeiten/products-api-client <https://github.com/goldene-zeiten/products-api-client>`__ package
— the same infrastructure other Products payment/carrier integrations build on. Unlike PayPal, Klarna
does not use OAuth: every call is authenticated with a single HTTP Basic header built from the
configured username and password (see :ref:`configuration`), so this extension needs only the shared
package's plain HTTP client, not its OAuth token provider.

Scope of this release
========================

This release covers the **pay flow** only: opening the session, redirecting to Klarna's Hosted Payment
Page, placing the order once authorized, and confirming via the status callback. Refunds and
cancellations from the backend order module (the core :php:`RefundablePaymentMethodInterface`) are a
planned later phase — see :ref:`developer-future-refunds` for the extension point this release does
ship.
