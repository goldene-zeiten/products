..  _start:

================
Products Wishlist
================

:Extension key:
    products_wishlist

:Package name:
    goldene-zeiten/products-wishlist

:Version:
    |release|

:Language:
    en

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

----

A wishlist for the Products shop system: an add-to-wishlist toggle on product listings and detail pages,
and a wishlist page that lists, reorders and removes saved products.

----

What it does
============

Once installed and enabled, a wishlist toggle appears on the core product list and detail views (through
the catalog plugins), and the :guilabel:`Wishlist` plugin renders the saved products with reorder and
remove actions. A guest's wishlist lives in the session and is merged into their account wishlist on login;
placing an order clears the wishlist. Without this extension the core catalog shows no wishlist affordance.

Installation
============

..  code-block:: bash

    composer require goldene-zeiten/products-wishlist

Add the :guilabel:`Products Wishlist` site set to your site, place the :guilabel:`Wishlist` plugin on the
wishlist page, and point ``products.pids.wishlistPage`` at it. Enable it with
:confval:`products.wishlist.enabled`.

Settings
========

..  confval:: products.wishlist.enabled
    :type: bool
    :Default: false

    Show the add-to-wishlist affordance on product listings.
