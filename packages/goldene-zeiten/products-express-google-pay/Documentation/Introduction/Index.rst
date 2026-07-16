:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_express_google_pay adds Google Pay as a one-tap express checkout to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop, using the Google Pay API for Web
directly — no PSP wrapper. It plugs into the core express-checkout provider seam
(:php:`GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface`): the button opens the sheet on
the **cart page, before an order exists**, Google supplies the address, and shipping is computed live.

..  contents:: Table of contents
    :local:

Google Pay needs a processor
============================

Google Pay hands the browser a payment **token**, encrypted for a tokenization gateway; some processor must
charge it. This extension is deliberately gateway-agnostic — it settles the token through a processor **you**
configure (your acquirer or PSP), not through a fixed provider. See :ref:`developer-processor-contract` for
the call the processor must answer.

The express flow
================

1.  **Live shipping.** As the buyer picks an address in the sheet, Google Pay's ``onPaymentDataChanged``
    callback is answered directly from the core shipping-quote endpoint (proven by the signed basket token),
    so the shop's own carriers, costs and the running total appear in the sheet — the same source of truth
    the in-shop checkout uses.

2.  **Authorize.** When the buyer authorizes, the shop recomputes the amount from its **own** basket and
    shipping, authorizes the token through the processor, and only on approval creates the paid order
    through the same order-creation and finalization services the normal checkout runs on. It answers with
    the thank-you URL the browser is sent to.

Because the amount is recomputed on the server before authorization, the buyer is charged exactly the
server-computed total, never a figure from the client.

Browser support
===============

The Google Pay button appears in Chrome, Safari, Firefox, Edge and others where Google Pay is available; on
browsers or devices without it, the button does not appear and the shopper continues through the normal
checkout.

Scope of this release
=====================

This release covers the **pay flow** only: live shipping, token authorization and paid-order creation. The
authorize-and-create path is covered end-to-end by a functional test against a processor mock — see
:ref:`developer-testing`.
