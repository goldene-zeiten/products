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
        A category with its own :guilabel:`Notification email` set (see
        :ref:`Category-level order notifications <users-manual-category-notifications>`) is routed
        there in addition to this sitewide recipient, not instead of it.

    ..  confval:: products.email.orderStatusChanged.enabled
        :type: bool
        :Default: true

        Whether the customer gets an email when an order's status changes (see
        :ref:`Managing orders <users-manual-orders>`).

    ..  confval:: products.stock.lowStockThreshold
        :type: int
        :Default: 5

        Stock level at or below which placing an order sends a low-stock warning email to
        `products.email.merchantRecipient`; 0 means only a stock-out (0 or below) triggers it.

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

    ..  confval:: products.invoice.templateRootPaths
        :type: stringlist
        :Default: EXT:products/Resources/Private/Templates/Invoice/

    ..  confval:: products.invoice.partialRootPaths
        :type: stringlist
        :Default: EXT:products/Resources/Private/Partials/

    ..  confval:: products.invoice.layoutRootPaths
        :type: stringlist
        :Default: EXT:products/Resources/Private/Layouts/

        Override any of the three invoice path settings from your own extension to replace the
        shipped invoice PDF template. See :ref:`Invoice PDF <users-manual-invoice>`.

    ..  confval:: products.invoice.pageType
        :type: int
        :Default: 1729512001

        The page type used to render the invoice PDF download outside the page's normal HTML
        shell, the same trick `products.ajax.pageType` uses for AJAX responses.

    ..  confval:: products.ajax.pageType
        :type: int
        :Default: 1729512000

        The AJAX page type used by the :rst:dir:`ProductList` plugin's cached AJAX loading mode.

    ..  confval:: products.catalog.articleSwitchMode
        :type: string
        :Default: reload

        Either ``reload`` (submitting the variant selector reloads the page) or ``ajax`` (the
        price/stock fragment is swapped in place without a full reload). See
        :ref:`Variant attributes <users-manual-variants>`.

    ..  confval:: products.creditPoints.enabled
        :type: bool
        :Default: false

        Whether the credit-points loyalty mechanic is active at all. See
        :ref:`Credit points <users-manual-credit-points>`.

    ..  confval:: products.creditPoints.moneyPerPoint
        :type: number
        :Default: 0.10

        Money value of one credit point when a customer spends it at checkout.

    ..  confval:: products.shipping.enabled
        :type: bool
        :Default: false

        Whether the checkout asks for a shipping method and adds its cost to the order total. See
        :ref:`Shipping costs <users-manual-shipping>`.

    ..  confval:: products.shipping.bulkySurcharge
        :type: string
        :Default: 0.00

        Flat surcharge added once per bulky-flagged basket item unit, on top of the chosen
        shipping method's rate - survives a free-shipping voucher, since an oversized item still
        costs extra to handle regardless of who pays the base rate. ``0.00`` disables it.

    ..  confval:: products.handling.enabled
        :type: bool
        :Default: false

        Whether a handling fee is calculated automatically from the configured
        :guilabel:`Handling Fee` records, added to the order total the same way shipping cost is.
        Unlike shipping, this is never shopper-chosen.

    ..  confval:: products.vouchers.gained.enabled
        :type: bool
        :Default: false

        Whether placing a qualifying order automatically issues a reward voucher. See
        :ref:`Gained bonus vouchers <users-manual-gained-vouchers>`.

    ..  confval:: products.vouchers.gained.minimumOrderValue
        :type: number
        :Default: 0.00

        Minimum order total (gross) required to trigger a gained voucher.

    ..  confval:: products.vouchers.gained.rewardType
        :type: string
        :Default: fixed

        Either ``fixed`` or ``percentage`` — the discount type of an auto-issued gained voucher.

    ..  confval:: products.vouchers.gained.rewardValue
        :type: number
        :Default: 5.00

        The discount value of an auto-issued gained voucher, interpreted per ``rewardType``.

    ..  confval:: products.wishlist.enabled
        :type: bool
        :Default: false

        Whether product listings show an "add to wishlist" link. See
        :ref:`Wishlist <users-manual-wishlist>`.

    ..  confval:: products.recentlyViewed.limit
        :type: int
        :Default: 10

        Maximum number of recently-viewed products remembered per visitor.

    ..  confval:: products.search.resultsPerPage
        :type: int
        :Default: 20

        Results shown per page by the :rst:dir:`Search` plugin.

..  _configuration-backend-module:

Backend module
===============

The :guilabel:`Products` main module (with the :guilabel:`Products` submodule) manages categories,
products and articles in a dedicated tree view, independent of the page tree. Backend users and
groups can be restricted to a subset of the category tree via the :guilabel:`Category mounts` field
on their user/group record, mirroring how page mounts restrict the classic page tree.
