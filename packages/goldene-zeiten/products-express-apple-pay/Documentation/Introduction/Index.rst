:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_express_apple_pay adds Apple Pay as a one-tap express checkout to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop, using the raw Apple Pay JS
``ApplePaySession`` directly — no PSP wrapper. It plugs into the core express-checkout provider seam
(:php:`GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface`): the button opens the sheet on
the **cart page, before an order exists**, Apple supplies the address, and shipping is computed live.

..  contents:: Table of contents
    :local:

Apple Pay needs a processor
===========================

Apple Pay is a card-presentation layer, not a payment processor: it returns an **encrypted payment token**,
which some processor must decrypt and charge. This extension is deliberately gateway-agnostic — it settles
the token through a processor **you** configure (your acquirer or PSP), not through a fixed provider. That
is what makes it "plain" Apple Pay: your own merchant account, your own processor. See
:ref:`developer-processor-contract` for the two calls the processor must answer.

The express flow
================

1.  **Validate.** When the buyer taps the button, Apple asks the shop to validate the merchant session. The
    shop forwards Apple's validation URL to the processor (which holds the Apple Pay merchant certificate
    the browser cannot) and hands the resulting session back to the sheet.

2.  **Live shipping.** As the buyer picks an address, the sheet's shipping callbacks are answered directly
    from the core shipping-quote endpoint (proven by the signed basket token), so the shop's own carriers,
    costs and the running total appear in the sheet — the same source of truth the in-shop checkout uses.

3.  **Authorize.** On authorization, the shop recomputes the amount from its **own** basket and shipping,
    authorizes the encrypted token through the processor, and only on approval creates the paid order
    through the same order-creation and finalization services the normal checkout runs on. It answers with
    the thank-you URL the browser is sent to.

Because the amount is recomputed on the server before authorization, the buyer is charged exactly the
server-computed total, never a figure from the client.

Browser support
===============

Raw ``ApplePaySession`` is a Safari / WebKit feature (iOS and macOS Safari). On other browsers the button
does not appear and the shopper continues through the normal checkout — install this **alongside** other
wallets if you want to cover non-Safari browsers.

Scope of this release
=====================

This release covers the **pay flow** only: merchant validation, live shipping, token authorization and
paid-order creation. The authorize-and-create path is covered end-to-end by a functional test against a
processor mock — see :ref:`developer-testing`.
