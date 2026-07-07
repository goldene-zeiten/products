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

The extension registers eight content element plugins:

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

    A simple catalog search across title, item number, description and EAN.

Payment
=======

Payment methods are registered against :php:`GoldeneZeiten\Products\Payment\PaymentMethodRegistry`
by implementing :php:`PaymentMethodInterface` and tagging the service, so third-party extensions can
add further payment methods without modifying this extension. Only invoice payment
(:php:`InvoicePaymentMethod`) ships out of the box.

Backend module
==============

A dedicated :guilabel:`Products` backend module manages the category tree, products and articles
outside of the classic page-tree-bound list module, since products in this extension are not
organised per page. See :ref:`Configuration <configuration>` for the storage folder setting that
controls where records are created.
