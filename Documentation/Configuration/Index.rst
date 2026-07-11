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

    ..  confval:: products.pids.categoryPage
        :type: int
        :Default: 0

        Page uid the category navigation/listing links resolve against.

    ..  confval:: products.pids.withdrawalPage
        :type: int
        :Default: 0

        Page uid the self-service order withdrawal/cancellation link (sent in the order
        confirmation and shown on the thank-you/order-detail pages) points to. See
        :ref:`Order withdrawal <users-manual-withdrawal>`.

    ..  confval:: products.pricing.mode
        :type: string
        :Default: gross

        Whether catalog prices are entered as ``gross`` or ``net``.

    ..  confval:: products.pricing.currency
        :type: string
        :Default: EUR

        ISO 4217 currency code orders are placed in.

    ..  confval:: products.pricing.discountFieldMode
        :type: string
        :Default: maxAcrossTree

        Either ``maxAcrossTree`` (the highest non-disabled category discount anywhere in the
        product's ancestor chain wins) or ``nearestCategory`` (the first non-disabled discount
        found walking up from the product's own category wins, stopping at the first hit). See
        :ref:`Category-cascading discounts <users-manual-category-discounts>`.

    ..  confval:: products.pricing.roundingMode
        :type: string
        :Default: none

        Rounds the basket/order gross total only (never per-line unit prices) to either
        ``nearestInteger`` or a ``psychological99`` charm price (e.g. 23.45 becomes 23.99).
        ``none`` keeps the exact computed total.

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

    ..  confval:: products.email.agbAttachment
        :type: string
        :Default: (empty)

        FAL combined file identifier (e.g. ``1:/legal/agb.pdf``) of a terms/AGB PDF attached to
        every order confirmation email, alongside the invoice PDF. Empty disables the attachment.
        There is no file-picker UI for this setting (TYPO3's Site Settings have no file-reference
        type) — the identifier must be typed in directly.

    ..  confval:: products.email.orderStatusChanged.enabled
        :type: bool
        :Default: true

        Whether the customer gets an email when an order's status changes (see
        :ref:`Managing orders <users-manual-orders>`).

    ..  confval:: products.email.orderBccRecipient
        :type: string
        :Default: (empty)

        A standing blind-copy recipient (e.g. an accounting/archival mailbox) added to every
        order confirmation, merchant notification, status-changed, and withdrawal email. Empty
        disables it.

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

    ..  confval:: products.creditPoints.earningMode
        :type: string
        :Default: perProduct

        One of ``perProduct`` (each line's own credit-point value, summed across the basket),
        ``basketTiered`` (the single highest-qualifying tier from `products.creditPoints.earningTiers`
        wins, not summed), or ``autoPriceFactor`` (a line with no explicit credit-point value earns
        points via `products.creditPoints.priceFactor` instead of 0; a line that does have one
        always keeps using it, even in this mode).

    ..  confval:: products.creditPoints.earningTiers
        :type: stringlist
        :Default: (empty)

        ``basketTiered`` mode thresholds as ``amount:points`` pairs, e.g. ``50.00:10``. The highest
        threshold at or below the basket total wins; malformed entries are silently skipped.

    ..  confval:: products.creditPoints.priceFactor
        :type: number
        :Default: 0.0

        ``autoPriceFactor`` mode: points earned per whole currency unit, for lines without their
        own explicit credit-points value.

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

    ..  confval:: products.shipping.bulkyNoticeText
        :type: string
        :Default: (empty)

        Notice shown in the basket/checkout when it contains a bulky-flagged item. Empty disables
        the notice entirely (regardless of `products.shipping.bulkySurcharge`).

    ..  confval:: products.shipping.weightNoticeThreshold
        :type: int
        :Default: 0

        Basket weight in grams above which `products.shipping.weightNoticeText` is shown. ``0``
        disables it.

    ..  confval:: products.shipping.weightNoticeText
        :type: string
        :Default: (empty)

        Notice shown in the basket/checkout once `products.shipping.weightNoticeThreshold` is
        exceeded. Empty disables it even if the threshold is set.

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

    ..  confval:: products.mostViewed.limit
        :type: int
        :Default: 10

        Maximum number of products shown in the site-wide and per-shopper "most viewed" listings
        (the :rst:dir:`RecentlyViewed` plugin's ``mostViewed``/``myMostViewed`` actions). See
        :ref:`Most viewed <users-manual-most-viewed>`.

    ..  confval:: products.search.resultsPerPage
        :type: int
        :Default: 20

        Results shown per page by the :rst:dir:`Search` plugin.

    ..  confval:: products.checkout.withdrawalPeriodDays
        :type: int
        :Default: 14

        Days after order placement a customer may self-service withdraw/cancel their order via the
        link on `products.pids.withdrawalPage`. ``0`` disables the feature entirely. See
        :ref:`Order withdrawal <users-manual-withdrawal>`.

    ..  confval:: products.seo.pageTitleMode
        :type: string
        :Default: title

        Controls how a product's :rst:dir:`ProductDetail` page ``<title>`` is built: ``none``
        (page title only), ``title`` (product title replaces the page title), ``subtitleOrTitle``
        (subtitle if set, else title), ``titleAndSubtitle`` or ``subtitleAndTitle`` (both, in the
        given order).

    ..  confval:: products.session.requireCookieConsent
        :type: bool
        :Default: false

        When enabled, the guest wishlist and recently-viewed session storages only write to the FE
        session once the visitor's browser already carries a confirmed FE-session cookie from an
        earlier request — never on the request that would be the first to set it. Off by default,
        preserving the previous unconditional-write behaviour.

..  _configuration-backend-module:

Backend module
===============

The :guilabel:`Products` main module (with the :guilabel:`Products` submodule) manages categories,
products and articles in a dedicated tree view, independent of the page tree. Backend users and
groups can be restricted to a subset of the category tree via the :guilabel:`Category mounts` field
on their user/group record, mirroring how page mounts restrict the classic page tree.
