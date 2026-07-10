:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products is a shop system built on Extbase/Fluid. It provides a product catalog with
categories and purchasable article variants, a basket, a guest-checkout-first payment flow, and
order history for logged-in customers.

..  contents:: Table of contents
    :local:

Frontend plugins
=================

The extension registers ten content element plugins:

..  confval:: ProductList

    Renders a list of products for a category, optionally loaded via a cached AJAX request.

..  confval:: ProductDetail

    Renders a single product, including its purchasable articles when the product has any.

..  confval:: Basket

    Shows, adds to, updates and removes items from the current visitor's basket.

..  confval:: Checkout

    The address, shipping method (when enabled), payment, review and thank-you steps of the
    checkout flow. Guest checkout is the default; a logged-in frontend user is optional.

..  confval:: OrderHistory

    Lists and shows past orders for the currently logged-in frontend user.

..  confval:: Wishlist

    Shows and manages the current visitor's saved products. See
    :ref:`Wishlist <users-manual-wishlist>`.

..  confval:: RecentlyViewed

    Shows the products the current visitor looked at most recently, independent of the
    :rst:dir:`ProductDetail` page — place it anywhere, e.g. a sidebar.

..  confval:: Search

    A simple catalog search across title, subtitle, item number, description and EAN.

..  confval:: Invoice

    Not meant to be placed by an editor - registered so the secured invoice PDF download link
    (see :ref:`Invoice PDF <users-manual-invoice>`) sent in order confirmation emails and shown in
    order history resolves to a real page.

..  confval:: Withdrawal

    Renders the self-service order cancellation form and confirmation reached from the "Cancel
    this order" link on the thank-you and order-detail pages. See
    :ref:`Order withdrawal <users-manual-withdrawal>`. Needs `products.pids.withdrawalPage`
    configured, the same way :rst:dir:`Checkout`/:rst:dir:`OrderHistory` need their own page
    settings.

Payment
=======

Payment methods are registered against :php:`GoldeneZeiten\Products\Payment\PaymentMethodRegistry`
by implementing :php:`PaymentMethodInterface` and tagging the service, so third-party extensions can
add further payment methods without modifying this extension. Only invoice payment
(:php:`InvoicePaymentMethod`) ships out of the box.

Pricing
=======

The unit price for a basket line is resolved by a small decorator chain behind
:php:`GoldeneZeiten\Products\Pricing\PriceProviderInterface`:
:php:`ProductPriceProvider` (the base article/product price) is wrapped by
:php:`GraduatedPriceProvider` (quantity-based tiers), which is in turn wrapped by
:php:`CategoryDiscountPriceProvider` — the actual DI alias for :php:`PriceProviderInterface`. That
outermost step compares the shopper's :ref:`FE-usergroup discount
<users-manual-usergroup-discounts>` against the product's own
:ref:`category-cascading discount <users-manual-category-discounts>` and applies whichever rate is
higher, never both. Each step decorates the concrete class beneath it (not the interface), so the
order is fixed at this one binding in ``Services.yaml`` rather than discoverable/pluggable; a shop
needing a genuinely different pricing strategy overrides that alias.

..  _introduction-order-export:

Order export
============

Third-party extensions can offer shop operators an export format (CSV, a specific ERP's import
format, a marketplace feed, ...) by implementing
:php:`GoldeneZeiten\Products\Export\OrderExportInterface` and tagging the service - the same
tagged-service pattern payment methods use. :php:`OrderExportRegistry` collects every registered
exporter; this extension ships no exporter of its own, since the format an operator actually
needs depends entirely on what they integrate with.

Backend module
==============

A dedicated :guilabel:`Products` backend module manages the category tree, products and articles
outside of the classic page-tree-bound list module, since products in this extension are not
organised per page. See :ref:`Configuration <configuration>` for the storage folder setting that
controls where records are created.
