.. include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Place the badge
===============

Reference one of the two TypoScript objects from your page template, for example as a
``FLUIDTEMPLATE`` variable of the page:

..  code-block:: typoscript

    page.10.variables.basket < lib.productsBasketSummary

``lib.productsBasketSummary``
    The cache-safe badge. It renders empty and fills itself in the browser. Use it on cached pages.

``lib.productsBasketSummaryUncached``
    The same badge rendered on the server with the current count and total. It makes the part of
    the page it sits in uncached.

The badge links to the page set in the site setting ``products.pids.basketPage`` of the core
extension.

Markup and styling
==================

Override the partial ``BasketSummary/Badge.html`` through the TypoScript paths
``plugin.tx_productsbasketsummary.view.partialRootPaths`` (server-rendered badge) and
``lib.productsBasketSummary.partialRootPaths`` / ``templateRootPaths`` (cache-safe badge). The
script needs the ``<products-basket-summary endpoint="…">`` element around the link; inside it
fills the element marked ``data-basket-count`` (hidden while the basket is empty) and the one
marked ``data-basket-total``, and sets the link title to the formatted total.

After changing the basket without a page load, dispatch ``products:basket-updated`` on
``window`` to refresh the badge.

The endpoint
============

``GET /products/basket-summary`` answers with the visitor's basket:

..  code-block:: json

    {"count": 3, "total": "160.00", "currency": "EUR"}

The response is never cached (``Cache-Control: no-store, private``). The path is fixed and
resolved before page routing, so it must be reachable on the domain root of the site.
