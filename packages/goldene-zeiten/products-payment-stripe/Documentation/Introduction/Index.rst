:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_payment_stripe adds Stripe Checkout as a payment method to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop. It plugs into the core shop's
existing Payment Methods API (:php:`GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface`,
documented in the ``products-core`` extension's own :file:`Documentation/Developer/Api/PaymentMethods.rst`)
— the same seam :guilabel:`Invoice` uses — so it simply appears alongside invoice and any other configured
method at checkout, without any change to the shop's checkout flow.

..  contents:: Table of contents
    :local:

The payment flow
=================

:php:`GoldeneZeiten\Products\Payment\Stripe\Payment\StripePaymentMethod` implements the core
:php:`RedirectPaymentMethodInterface` on top of the official
`stripe/stripe-php <https://github.com/stripe/stripe-php>`__ SDK. Redirect payment methods have three
moving parts, and this extension implements all three:

1.  **`initiate()`** creates a Stripe `Checkout Session
    <https://docs.stripe.com/api/checkout/sessions>`__ (mode :code:`payment`, one line item for the order
    total) and returns :php:`PaymentResult::redirectRequired()` with Stripe's hosted checkout URL
    (`session.url`). The customer's browser is sent there to pay.

2.  **`handleReturn()`** runs when Stripe sends the customer's browser back to the shop's checkout. The
    session's :code:`success_url` carries Stripe's own :code:`{CHECKOUT_SESSION_ID}` placeholder as a bare
    :code:`session_id` query parameter (not the shop's own signed callback token, which lives under the
    checkout plugin's own namespace — the two never collide). The method reads that session id back and
    confirms it **server-to-server** by retrieving the session from Stripe; only a :code:`payment_status`
    of :code:`paid` marks the order paid.

3.  **`handleWebhook()`** runs independently, whenever Stripe posts an asynchronous event to the shop's
    fixed payment webhook middleware (registered by ``products-core`` at path
    :code:`/products/payment/webhook`). Before trusting it, the extension verifies the webhook's signature
    against the configured webhook signing secret via Stripe's own SDK
    (:php:`\Stripe\Webhook::constructEvent()` — see :ref:`introduction-webhook-verification` below); an
    unverified or forged notification is never allowed to mark an order paid. Only the
    :code:`checkout.session.completed` event type is acted on; any other event is acknowledged as pending.

Both callbacks are idempotent, as the core API requires: an order that is already
:php:`PaymentStatus::PAID` is not re-confirmed against Stripe on a replayed return, and interpreting a
session's :code:`payment_status` is safe to run more than once for the same session.

..  _introduction-webhook-verification:

Webhook signature verification
================================

`handleWebhook()` verifies an incoming webhook by calling
:php:`\Stripe\Webhook::constructEvent()` with the raw request body, the :code:`Stripe-Signature` header,
and the configured `products.payment.stripe.webhookSecret` — this is the same "verify with the gateway,
never trust the caller" rule the core Payment Methods API requires of every redirect method. A body/
signature pair that does not verify (or an empty webhook secret) is rejected with
:php:`PaymentResult::failed()`; the browser return alone still confirms payment in that case.

Card, Apple Pay, Google Pay and Wero — automatically
========================================================

This extension does not itemise or select payment methods itself: Stripe Checkout offers whichever
payment methods are enabled and eligible on the Stripe account, for the session's currency and the
shopper's device. In practice this means, with no extra code or configuration here:

*   **Card** is always offered.
*   **Apple Pay**, **Google Pay** and **Wero** are offered automatically wherever the shopper's browser/
    device supports them, once the shop's checkout domain is registered under Stripe's
    `Payment Method Domains <https://docs.stripe.com/payments/payment-methods/pmd-registration>`__ — see
    :ref:`configuration-payment-method-domains`.

Availability at checkout
==========================

Stripe is only offered once it is actually usable: `isAvailable()` requires both a configured secret key
(see :ref:`configuration`) and a non-empty order currency. An unconfigured installation therefore shows no
Stripe option at all, rather than offering a method that would fail as soon as the customer picked it.

Scope of this release
========================

This release covers the **pay flow** only: creating the Checkout Session, confirming it, and confirming
via webhook. Refunds and cancellations from the backend order module (the core
:php:`RefundablePaymentMethodInterface`) are a planned later phase — see
:ref:`developer-future-refunds` for the extension point this release does ship.
