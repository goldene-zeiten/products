:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_express_stripe adds Stripe's Express Checkout Element as a one-tap express checkout to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop. It plugs into the core shop's
express-checkout provider seam (:php:`GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface`),
which is a different seam from the normal Payment Methods API: an express button opens a wallet sheet on the
**cart page, before an order or a chosen shipping option exists**, and the wallet supplies the address while
shipping is quoted live.

..  contents:: Table of contents
    :local:

Why express checkout is its own seam
=====================================

The multi-step checkout collects the address, then a shipping option, then a payment method, and only then
places the order. Express checkout inverts that: the shopper taps a wallet button, the wallet hands the shop
a shipping address, and the shop must quote shipping and show a running total **inside the wallet sheet**
before anything is charged. Core owns the shared machinery every express provider reuses — the signed basket
token, the live shipping-rate quote and the express order creation — so this extension only renders Stripe's
button and settles the payment.

The express flow
================

1.  **The button.** The :guilabel:`Products: Stripe Express Checkout` plugin, placed on the cart page,
    renders the Express Checkout Element for the live basket. It hands the button JS a per-basket **signed
    token** (a tamper-proof snapshot of the basket's shipping-relevant facts) so the later callbacks can
    trust the basket without a session.

2.  **Live shipping.** When the shopper picks an address in the wallet sheet, the JS posts the token and the
    (street-redacted) destination to the core express shipping-rate endpoint
    (:code:`/products/express/shipping-quote`). It answers with the shop's own carrier options and costs —
    the same ones the in-shop checkout would show — and the sheet's total is kept in step with the chosen
    option.

3.  **Confirm.** On confirmation the JS creates a Stripe PaymentMethod from the wallet and posts it, with the
    wallet address and the chosen shipping option, to this extension's confirm endpoint. The server
    **recomputes the amount from its own basket and shipping** (never the client's), settles it as a
    Stripe `PaymentIntent <https://docs.stripe.com/api/payment_intents>`__, and only on success creates the
    paid order through the very same order-creation and finalization services the normal checkout runs on.
    It answers with the thank-you URL the browser is sent to.

Because the charge is settled before the order exists, the amount is computed the same way the shipping-rate
callback quoted it (goods total plus the chosen carrier cost), and order creation reruns the identical quote
— so the shopper is charged exactly what the wallet sheet showed.

One button, every wallet
========================

A single Express Checkout Element renders Apple Pay, Google Pay, PayPal, Amazon Pay and Link. Stripe decides
which buttons to show per browser and device, and handles Apple Pay merchant validation and wallet
tokenisation — so this extension surfaces every supported wallet with no per-wallet code.

Availability
============

The button is only offered when Stripe Express can both render it and settle it: a publishable **and** a
secret key must be configured (see :ref:`configuration`), and the basket currency must be one Stripe's
Express Checkout Element supports. A half-configured or unsupported-currency shop simply shows no button
rather than a button that fails when tapped.

Scope of this release
=====================

This release covers the **pay flow** only: rendering the button, quoting shipping live, settling the wallet
payment and creating the paid order. A real wallet sheet (Apple Pay / Google Pay) is a device-and-browser
feature that cannot be exercised in a headless browser, so the settle-and-create path is covered end-to-end
by a functional test against a Stripe API mock rather than by a browser test — see :ref:`developer-testing`.
