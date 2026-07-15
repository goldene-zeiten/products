:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_payment_paypal adds PayPal Checkout as a payment method to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop. It plugs into the core shop's
existing Payment Methods API (:php:`GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface`,
documented in the ``products-core`` extension's own :file:`Documentation/Developer/Api/PaymentMethods.rst`)
— the same seam :guilabel:`Invoice` uses — so it simply appears alongside invoice and any other
configured method at checkout, without any change to the shop's checkout flow.

..  contents:: Table of contents
    :local:

The payment flow
=================

:php:`GoldeneZeiten\Products\Payment\Paypal\Payment\PaypalPaymentMethod` implements the core
:php:`RedirectPaymentMethodInterface`. Redirect payment methods have three moving parts, and this
extension implements all three:

1.  **`initiate()`** creates a PayPal `Orders v2 <https://developer.paypal.com/docs/api/orders/v2/>`__
    order for the order total (intent :code:`CAPTURE`) and returns
    :php:`PaymentResult::redirectRequired()` with PayPal's approval URL. The customer's browser is
    sent there to log in and approve the payment.

2.  **`handleReturn()`** runs when PayPal sends the customer's browser back to the shop's checkout.
    PayPal appends the PayPal order id as a bare :code:`token` query parameter (not the shop's own
    signed callback token, which lives under the checkout plugin's own namespace — the two never
    collide). The method captures that order **server-to-server** against the PayPal API and, once
    PayPal confirms the capture, marks the order paid.

3.  **`handleWebhook()`** runs independently, whenever PayPal posts an asynchronous event to the
    shop's fixed payment webhook middleware (registered by ``products-core`` at path
    :code:`/products/payment/webhook`). Before trusting it, the extension verifies the webhook's
    transmission signature against PayPal itself (see :ref:`introduction-webhook-verification`
    below) — an unverified or forged notification is never allowed to mark an order paid.

Both callbacks are idempotent, as the core API requires: an order that is already
:php:`PaymentStatus::PAID` is not captured again on a replayed return, and a capture that PayPal
reports as already done (:code:`ORDER_ALREADY_CAPTURED`) is itself treated as a successful capture
rather than an error.

..  _introduction-webhook-verification:

Webhook signature verification
================================

:php:`GoldeneZeiten\Products\Payment\Paypal\Webhook\PaypalWebhookVerifier` confirms an incoming
webhook by calling PayPal's own
:code:`POST /v1/notifications/verify-webhook-signature` endpoint with the transmission headers
PayPal signed the request with and the configured `products.payment.paypal.webhookId` — this is
the same "verify with the gateway, never trust the caller" rule the core Payment Methods API
requires of every redirect method. Without a configured webhook id nothing can be verified, so the
webhook is always rejected in that case; the browser return alone still confirms payment.

Availability at checkout
==========================

PayPal is only offered once it is actually usable: `isAvailable()` requires both a configured
client id and client secret (see :ref:`configuration`) and a currency PayPal settles in. An
unconfigured installation therefore shows no PayPal option at all, rather than offering a method
that would fail as soon as the customer picked it.

Shared API infrastructure
===========================

OAuth 2.0 client-credentials tokens and outbound HTTP calls go through the shared
`goldene-zeiten/products-api-client <https://github.com/goldene-zeiten/products-api-client>`__
package — the same infrastructure other Products payment/carrier integrations build on. This
extension owns only the PayPal-specific request/response shapes and its own token cache.

Scope of this release
========================

This release covers the **pay flow** only: creating the PayPal order, capturing it, and confirming
via webhook. Refunds and cancellations from the backend order module (the core
:php:`RefundablePaymentMethodInterface`) are a planned later phase — see
:ref:`developer-modify-order-request` for the extension point this release does ship.
