:navigation-title: Introduction

..  include:: /Includes.rst.txt
..  _introduction:

============
Introduction
============

EXT:products_wishlist adds a wishlist to the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop: shoppers add products to a
personal list from the catalog and manage that list on its own page. It plugs into the core
catalog's existing extension point rather than changing any core class, so the core shop works
exactly as before when this extension is absent.

..  contents:: Table of contents
    :local:

..  _introduction-catalog-integration:

Catalog integration
=====================

:php:`GoldeneZeiten\Products\Wishlist\EventListener\EnrichProductViewWithWishlistListener` listens
to the core's :php:`GoldeneZeiten\Products\Core\Event\EnrichProductViewEvent`, dispatched by the
core catalog controller for every product list and detail view. The listener adds three template
variables — whether the wishlist is enabled, the uids of the current visitor's wishlisted products,
and the current wishlist count — which the shipped :file:`Product/WishlistToggle.html` partial uses
to render an "add to wishlist" or "remove from wishlist" link per product.

That partial is registered at a higher path priority than the core's own (empty) partial of the
same name, so it silently replaces the core's no-op stub once this extension is installed — no
template override or TypoScript change is needed in the site itself.

..  _introduction-storage:

Guest and logged-in storage
==============================

Where a wishlist is stored depends on whether the visitor is logged in, resolved per request by
:php:`GoldeneZeiten\Products\Core\Service\FrontendUserResolver`:

*   A **guest** (no logged-in frontend user) gets a session-only wishlist:
    :php:`GoldeneZeiten\Products\Wishlist\Service\WishlistStorage` stores the product uids as JSON
    under the ``tx_products_wishlist`` key in the frontend user session. It is lost once the
    session expires and is never shared across devices or browsers.
*   A **logged-in** frontend user gets a persisted wishlist: one
    :php:`GoldeneZeiten\Products\Wishlist\Domain\Model\WishlistItem` row per saved product in
    ``tx_products_domain_model_wishlistitem``, linked to the ``fe_users`` record. It follows the
    customer across visits and devices.

:php:`GoldeneZeiten\Products\Wishlist\Service\WishlistService` is the single entry point that picks
the right backend for the current request — controllers and listeners never talk to
:php:`WishlistStorage` or :php:`WishlistItemRepository` directly.

..  _introduction-login-merge:

Merging the session wishlist on login
========================================

:php:`GoldeneZeiten\Products\Wishlist\EventListener\MergeWishlistOnLoginListener` listens to the
core's :php:`TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent`. When a frontend user logs
in, every product still in their session wishlist is added to their now-identified persisted
wishlist (skipping anything already there), and the session copy is cleared — so a guest who
builds a wishlist before creating an account or logging in does not lose it. A merge failure is
logged and never blocks the login itself.

..  _introduction-order-purge:

Clearing ordered items
========================

:php:`GoldeneZeiten\Products\Wishlist\EventListener\PurgeWishlistOnOrderPlacedListener` listens to
the core's :php:`GoldeneZeiten\Products\Core\Event\AfterOrderPlacedEvent` and removes every ordered
product from the placing customer's persisted wishlist. Guest orders are skipped, since a guest's
session wishlist is not tied to any identity an order could be matched against. A purge failure is
logged and never rolls back the order placement.

..  _introduction-frontend-plugin:

Frontend plugin
=================

..  confval:: Wishlist

    Shows the current visitor's wishlist (:php:`WishlistController::showAction()`), and handles
    adding, removing and reordering products on it. See :ref:`Configuration <configuration>` for
    how to place it, and :ref:`Users Manual <users-manual-wishlist>` for what the shopper sees.
