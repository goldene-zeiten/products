:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_payment_amazon adds Amazon Pay as a payment method to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop, via Amazon Checkout v2. It plugs into the core shop's existing Payment Methods API
(:php:`GoldeneZeiten\Products\Core\Payment\RedirectPaymentMethodInterface`, documented in the
``products-core`` extension's own :file:`Documentation/Developer/Api/PaymentMethods.rst`) — the same
seam :guilabel:`Invoice`, Klarna and PayPal use — so it simply appears alongside them at checkout, without any
change to the shop's checkout flow.

..  contents:: Table of contents
    :local:

The payment flow
=================

:php:`GoldeneZeiten\Products\Payment\Amazon\Payment\AmazonPayPaymentMethod` implements the core
:php:`RedirectPaymentMethodInterface`. Redirect payment methods have three moving parts, and this
extension implements all three:

1.  **`initiate()`** creates an Amazon Checkout Session (:code:`POST /checkout/sessions`) for the
    order total, then redirects the buyer to Amazon's authorization page. It returns
    :php:`PaymentResult::redirectRequired()` with the return URL for step 2. The customer's browser is sent
    there to authenticate with Amazon and authorize the payment.

2.  **`handleReturn()`** runs when Amazon sends the customer's browser back to the shop's checkout (the
    first return). It reads the session (:code:`GET /checkout/sessions/{id}`) to verify the buyer's
    address and finalize the order amount, updates the session with the final total
    (:code:`PATCH /checkout/sessions/{id}`), then redirects back to Amazon so the buyer reviews
    and completes the payment — this is the **two-hop redirect** flow. On the second return, the extension
    completes the payment (:code:`POST /checkout/sessions/{id}/complete-checkout-session`) and
    interprets Amazon's charge status: a completed charge marks the order paid, a pending charge leaves
    it pending, and any failure marks it failed.

3.  **`handleWebhook()`** runs independently, whenever Amazon posts its asynchronous status callback to
    the shop's fixed payment webhook middleware (registered by ``products-core`` at path
    :code:`/products/payment/webhook`). Amazon signs this callback with a signature the extension
    verifies against the configured public key. A verified COMPLETED callback ensures the order is marked
    paid even if the second return never arrives.

Both callbacks are idempotent, as the core API requires: an order that is already
:php:`PaymentStatus::PAID` is not placed again on a replayed return or webhook — the webhook handler
verifies the charge is complete before marking the order paid.

The two return legs (review-and-complete page vs. after-complete page) are distinguished by a query
parameter, so a single return URL suffices for both.

Availability at checkout
==========================

Amazon Pay is only offered once it is actually usable: `isAvailable()` requires the public key ID,
private key, store ID and store name to be configured (see :ref:`configuration`), and an order currency
Amazon settles in. An unconfigured installation, or an order in an unsupported currency, therefore
shows no Amazon Pay option at all, rather than offering a method that would fail as soon as the customer
picked it.

Request signing
================

Every outbound HTTP request is RSA-signed using the **RSASSA-PSS** algorithm and the configured
private key, per Amazon's authentication scheme. The extension uses the official
`amzn/amazon-pay-api-sdk-php <https://github.com/amzn/amazon-pay-api-sdk-php>`__ library for signing
only — HTTP transport and session management go through the shared
`goldene-zeiten/products-api-client <https://github.com/goldene-zeiten/products-api-client>`__ package.

Shared API infrastructure
===========================

Outbound HTTP calls go through the shared
`goldene-zeiten/products-api-client <https://github.com/goldene-zeiten/products-api-client>`__ package
— the same infrastructure other Products payment/carrier integrations build on. Requests are signed with
the private key; no OAuth token provider is needed.

Scope of this release
========================

This release covers the **pay flow** only: creating the session, the two-hop redirect flow for
review and completion, and confirmation via the webhook callback. Refunds and cancellations from the
backend order module (the core :php:`RefundablePaymentMethodInterface`) are a planned later phase.
