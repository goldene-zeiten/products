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

Activate the :guilabel:`Products` site set (``goldene-zeiten/products``) on every site that should
show the shop, then adjust its settings under :guilabel:`Site Management > Sites > Edit settings`.

..  _configuration-storage-folder:

Storage folder
--------------

None of this extension's records (categories, products, articles, tax classes/rates, orders) are
organised by page. `products.pids.storageFolder` is the single page uid new records are created in
by the backend module and by the upgrade wizards, and the pid the frontend plugins read the catalog
from. Set it before creating any content.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.pids.storageFolder
        :type: int
        :Default: 0

        Page uid the catalog (categories, products, articles) and orders are stored in.

    ..  confval:: products.pids.detailPage
        :type: int
        :Default: 0

        Page uid the :rst:dir:`ProductList` plugin links to for a product's detail view.

    ..  confval:: products.pids.basketPage
        :type: int
        :Default: 0

        Page uid the "add to basket" action redirects to.

    ..  confval:: products.pids.checkoutPage
        :type: int
        :Default: 0

        Page uid the checkout flow starts on.

    ..  confval:: products.pids.orderHistoryPage
        :type: int
        :Default: 0

        Page uid the :rst:dir:`OrderHistory` plugin lives on.

    ..  confval:: products.pricing.mode
        :type: string
        :Default: gross

        Whether catalog prices are entered as ``gross`` or ``net``.

    ..  confval:: products.pricing.currency
        :type: string
        :Default: EUR

        ISO 4217 currency code orders are placed in.

    ..  confval:: products.tax.defaultCountry
        :type: string
        :Default: DE

        ISO alpha-2 fallback country used for tax rate resolution when none is otherwise known.

    ..  confval:: products.tax.rounding
        :type: string
        :Default: perLine

        Either ``perLine`` (round each line item) or ``perTotal`` (round the order total once).

    ..  confval:: products.order.numberPrefix
        :type: string
        :Default: ORD-

        Prefix for generated order numbers.

    ..  confval:: products.order.storeClientIp
        :type: bool
        :Default: false

        Whether new orders record the placing visitor's IP address. GDPR-relevant, off by default.

    ..  confval:: products.email.senderEmail
        :type: string
        :Default: (empty)

        Sender address for order confirmation and merchant notification emails.

    ..  confval:: products.email.senderName
        :type: string
        :Default: (empty)

    ..  confval:: products.email.merchantRecipient
        :type: string
        :Default: (empty)

        Recipient for the merchant order notification. Leave empty to disable that email entirely.

    ..  confval:: products.email.templateRootPaths
        :type: stringlist
        :Default: EXT:products/Resources/Private/Templates/Email/

    ..  confval:: products.email.partialRootPaths
        :type: stringlist
        :Default: EXT:products/Resources/Private/Partials/

    ..  confval:: products.email.layoutRootPaths
        :type: stringlist
        :Default: EXT:products/Resources/Private/Layouts/

        Override any of the three email path settings from your own extension to replace the
        shipped order confirmation and merchant notification templates.

    ..  confval:: products.payment.invoice.enabled
        :type: bool
        :Default: true

        Whether the invoice payment method is offered at checkout.

    ..  confval:: products.ajax.pageType
        :type: int
        :Default: 1729512000

        The AJAX page type used by the :rst:dir:`ProductList` plugin's cached AJAX loading mode.

..  _configuration-backend-module:

Backend module
===============

The :guilabel:`Products` main module (with the :guilabel:`Products` submodule) manages categories,
products and articles in a dedicated tree view, independent of the page tree. Backend users and
groups can be restricted to a subset of the category tree via the :guilabel:`Category mounts` field
on their user/group record, mirroring how page mounts restrict the classic page tree.
