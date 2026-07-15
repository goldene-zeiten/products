:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_shipping_dhl_express plugs live DHL Express rates into the checkout of EXT:products_core,
through the core shop's shipping-provider seam. Instead of maintaining shipping-method records for DHL by
hand, the extension asks the DHL Express (MyDHL API) Rating endpoint for real products and prices for the
customer's actual basket and delivery address.

..  note::

    This extension covers **DHL Express**, the international courier product — the one DHL API that
    offers a public, machine-callable rate endpoint. **DHL Paket / Deutsche Post** domestic parcel
    shipping has no public rating API and is **not** covered by this package.

..  contents:: Table of contents
    :local:

..  _introduction-what-it-provides:

What it provides
=================

The extension registers one carrier,
:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Shipping\DhlExpressShippingProvider`, with the core
shop's carrier registry. At checkout it:

*   builds a DHL Express rate request from the basket's weight and the customer's delivery country/postcode,
*   authenticates with DHL Express using HTTP Basic credentials (the MyDHL API key and secret), over the
    shared :php:`goldene-zeiten/products-api-client` package's HTTP client,
*   maps every product DHL returns to a shipping option with a real price and, where DHL provides one, an
    estimated delivery date,
*   and lets the results be filtered to an allow-list of product codes.

This first release covers **rate quotes only**. Label printing and tracking are planned as a separate,
backend-side phase — see the package's ``DEVELOPERS.md`` for the current thinking.

..  _introduction-table-rate-fallback:

Relationship to table-rate shipping
=====================================

DHL Express is a real (non-fallback) carrier: it supersedes the shop's built-in table-rate shipping
methods whenever it returns at least one option for the basket. When DHL is unconfigured, unreachable,
returns an error, or simply has no rate for that shipment (for example a lane it will not serve), it
returns no options at all, and the table-rate shipping methods already configured in the shop serve the
basket instead — checkout never dead-ends just because DHL could not be reached. See
:ref:`How rating behaves <configuration-how-rating-behaves>` for the exact rules, and
:ref:`Developer <developer>` for the interface this fallback relies on
(:php:`GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface`, documented in EXT:products_core).

..  _introduction-when-to-use:

When to use this extension
============================

Install it whenever a shop wants to offer real DHL Express products and prices instead of, or alongside,
manually maintained shipping-method records — and has (or can get) a MyDHL API account. A shop with no DHL
Express account, or that only ever ships via its own fixed-price methods, has no need for it; the core
shop's table-rate shipping works standalone.
