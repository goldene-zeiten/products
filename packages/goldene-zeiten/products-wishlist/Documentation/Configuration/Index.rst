:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-site-set:

Site set
========

Activate the :guilabel:`Products Wishlist` site set (``goldene-zeiten/products-wishlist``) on every
site that should offer a wishlist. It declares a dependency on the core :guilabel:`Products` site
set (``goldene-zeiten/products-core``), so that one is pulled in automatically if not already
active on the site.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.wishlist.enabled
        :type: bool
        :Default: false

        Whether product listings and the detail view show the "add to wishlist"/"remove from
        wishlist" link (see :ref:`Catalog integration <introduction-catalog-integration>`). Off by
        default, so installing this extension changes nothing visible until an operator opts in.
        The :rst:dir:`Wishlist` content element itself works regardless of this setting — it only
        controls whether the link is injected into the catalog, so a shop can link to the wishlist
        page manually without necessarily surfacing it everywhere.

..  _configuration-wishlist-page:

Wishlist page
--------------

Set `products.pids.wishlistPage` (default ``0``) to the uid of the page carrying the
:rst:dir:`Wishlist` content element. The add-to-wishlist and remove-from-wishlist forms post there.
It is a site setting of this extension's site set; a site still using the classic static template sets
the TypoScript constant of the same name instead:

..  code-block:: typoscript
    :caption: TypoScript constants

    products.pids.wishlistPage = 42

..  _configuration-content-element:

Wishlist content element
===========================

The extension registers one content element plugin, :guilabel:`Wishlist`
(``ProductsWishlist`` / ``Wishlist``), via
:php:`TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin()`. Add it to a page like any
other content element (:guilabel:`Content` → :guilabel:`Create new content element` →
:guilabel:`Plugins` → :guilabel:`Wishlist`). It has no FlexForm and no content-element specific
fields beyond the standard header — the wishlist it shows is entirely determined by the visitor
making the request (their session, or their account), not by anything configured on the content
element record itself.
