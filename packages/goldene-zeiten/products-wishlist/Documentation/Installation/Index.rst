:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14.3
*   PHP 8.2, 8.3, 8.4 or 8.5
*   `goldene-zeiten/products-core` — the shop this wishlist plugs into

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-wishlist

Then activate the site set :guilabel:`Products Wishlist` (``goldene-zeiten/products-wishlist``) on
every site that should offer a wishlist — it depends on the core :guilabel:`Products` site set, so
activating it pulls that one in too if it is not already active.

Place the :guilabel:`Wishlist` content element on the page that should show the wishlist, point the
:ref:`wishlist page setting <configuration-wishlist-page>` at that page, and enable
`products.wishlist.enabled` — see :ref:`Configuration <configuration>` for both. Without the
setting enabled, the plugin still works if visited directly, but no "add to wishlist" link is
injected into the catalog for shoppers to find it.
