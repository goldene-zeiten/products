:navigation-title: Upgrading from tt_products

..  include:: /Includes.rst.txt
..  _upgrading:

==========================
Upgrading from tt_products
==========================

If the legacy ``tt_products`` extension (and its tables) are still present in the installation,
ten upgrade wizards under :guilabel:`Admin Tools > Upgrade` migrate its data into this
extension's tables. The migration wizards are idempotent (safe to run more than once) and skip
themselves entirely once the legacy tables are gone.

..  contents:: Table of contents
    :local:

Before you start
================

Some legacy behaviour lives in TypoScript (:sql:`sys_template`), which the wizards cannot read. Declare
it in the extension configuration of ``products_core`` (:guilabel:`Admin Tools > Settings > Extension
Configuration`, tab :guilabel:`migration`) before running the product and article wizards:

..  confval:: migration.legacyPrice2
    :name: upgrading-legacy-price2
    :type: options
    :default: undecided

    How the legacy shop used the ``price2`` column of products and articles. ``tt_products`` charges it
    whenever ``plugin.tt_products.priceNoReseller = 2`` is set, falling back to ``price`` where
    ``price2`` is empty.

    ``selling``
        ``priceNoReseller = 2`` was set for every visitor: ``price2`` becomes the price, ``price`` is
        used only where ``price2`` is empty.
    ``reseller``
        ``priceNoReseller = 2`` was set inside a usergroup condition: ``price`` stays the price, and
        ``price2`` becomes a price period of the frontend group set in
        :confval:`upgrading-legacy-price2-fe-group`, without start or end date.
    ``unused``
        ``price2`` is ignored; the wizards report how many records carried one.
    ``undecided``
        The product and article wizards refuse to run while any record has a ``price2``.

..  confval:: migration.legacyPrice2FeGroup
    :name: upgrading-legacy-price2-fe-group
    :type: int
    :default: 0

    Frontend group uid the ``price2`` price period applies to. Required when
    :confval:`upgrading-legacy-price2` is ``reseller``.

..  confval:: migration.storageFolderCategories
    :name: upgrading-storage-folder-categories
    :type: bool
    :Default: false

    For a legacy catalog grouped by storage folders rather than :sql:`tt_products_cat`. Enables
    the storage folder category wizard (see the run order) and makes the plugin wizard turn a
    legacy element's starting point into a selection of those categories.

..  confval:: migration.columnCategories
    :name: upgrading-column-categories
    :type: string
    :Default: (empty)

    Comma-separated ``column:Title`` pairs of ``tt_products`` columns the legacy shop grouped
    products by, e.g. ``form:Hersteller, quality:Filter``. Each column becomes a root category
    ``Title`` with one child per distinct value (the first spelling wins, regardless of case), and
    the plugin wizard turns a ``product_where`` filter like ``quality LIKE '%Weißgold%'`` - plain
    ``LIKE``/``=`` matches joined by ``OR`` - into a selection of those categories.

..  confval:: migration.columnCategoriesMultiValue
    :name: upgrading-column-categories-multi-value
    :type: string
    :Default: (empty)

    Columns of :confval:`upgrading-column-categories` holding several values, separated by commas,
    semicolons or line breaks.

..  confval:: migration.note2AsKeywords
    :name: upgrading-note2-as-keywords
    :type: bool
    :Default: false

    The legacy shop used ``note2`` for meta keywords. The product wizard then takes it as the
    product's keywords where ``tt_products.keyword`` is empty, which it always migrates.

..  confval:: migration.orderPaymentMethods
    :name: upgrading-order-payment-methods
    :type: string
    :Default: (empty)

    Maps the TypoScript keys of ``plugin.tt_products.payment`` to payment methods for the order
    migration, e.g. ``10:cashondelivery, 20:prepayment, 30:cash, 40:invoice, 90:paypal``. tt_products
    stores the key as the leading number of an order's ``payment`` text (``90: PayPal Gateway``).
    Orders with an unmapped key become ``invoice`` and are reported.

..  confval:: migration.orderShippingOptions
    :name: upgrading-order-shipping-options
    :type: string
    :Default: (empty)

    Maps the TypoScript keys of ``plugin.tt_products.shipping`` to a shipping provider and, optionally,
    an option, e.g. ``10:tablerate:1, 20:pickup``. The legacy shipping title becomes the order's
    shipping label. tt_products stores no pick-up branch, so ``pickup`` orders get none.

The order migration skips abandoned checkouts - rows tt_products created when a checkout started that
have no customer, no amount and no payment - and reports them.

The same values can be set on the command line, for example:

..  code-block:: bash

    vendor/bin/typo3 configuration:set EXTENSIONS/products_core/migration/legacyPrice2 selling

Run order
=========

Run the wizards in this order — each one links its rows back to the previous one via a shared
:sql:`tx_products_migration_map` table, so records must exist before they can be referenced:

#.  **Seed tax classes** (``products_initialTaxClasses``) — creates the standard/reduced/zero tax
    classes products are linked to.
#.  **Migrate categories** (``products_ttProductsCategoryMigration``) — ``tt_products_cat`` and its
    ``tt_products_cat_language`` translations.
#.  **Migrate products** (``products_ttProductsProductMigration``) — ``tt_products`` and its
    ``tt_products_language`` translations, linked to the migrated categories and tax classes.
#.  **Migrate articles** (``products_ttProductsArticleMigration``) — ``tt_products_articles`` and
    its ``tt_products_articles_language`` translations, linked to the migrated products. The product
    is read from ``uid_product`` and from :sql:`tt_products_products_mm_articles`, so every legacy
    article mode is covered. An article related to several products belongs to the first of them
    afterwards; the wizard reports every further product, whose variant has to be recreated by hand.
#.  **Turn storage folders into categories** (``products_ttProductsStorageFolderCategories``,
    only with :confval:`upgrading-storage-folder-categories`) — creates a category for every storage
    folder holding ``tt_products`` records, titled and slugged like the folder and nested like the
    folders up to the first page that is not a folder, and assigns every migrated product to the
    category of its folder. Run it after the product wizard and before the plugin wizard, which
    waits for it.
#.  **Turn grouping columns into categories** (``products_ttProductsColumnCategories``, only with
    :confval:`upgrading-column-categories`) — builds the category trees of the configured columns
    and assigns the migrated products. Run it after the product wizard and before the plugin
    wizard, which waits for it.
#.  **Migrate orders** (``products_ttProductsOrderMigration``) — ``sys_products_orders`` and its
    line items, linked to the migrated products/articles.
#.  **Migrate media** (``products_ttProductsMediaMigration``) — product/category/article images and
    product datasheets, linked to the already-migrated categories/products/articles. Run this
    before the cleanup wizard, since it needs the other wizards' :sql:`tx_products_migration_map`
    rows to know where each legacy record ended up.
#.  **Migrate visited-product counters** (``products_ttProductsVisitedProductsMigration``) —
    :sql:`sys_products_visited_products` (global view counts) and
    :sql:`sys_products_fe_users_mm_visited_products` (per-user view counts), remapping legacy
    product uids to migrated ones.
#.  **Migrate plugin content elements** (``products_ttProductsPluginMigration``) — rewrites the
    legacy FlexForm-driven plugin elements (:sql:`CType='list'` with :sql:`list_type` ``5``,
    ``tt_products_pi_int`` or ``tt_products_pi_search``) into this extension's single-purpose
    content types. A legacy element could select several display modes at once and rendered them
    one after another, so such an element becomes one new element per mode. Its product and
    category selections are remapped to the migrated records, which is why this wizard runs after
    the category and product wizards. Elements selecting a mode without an equivalent here (gift
    vouchers, the DAM and address-book families, the ``USER1``-``USER5`` hook slots) are left
    completely untouched and reported with their uid and page, for manual review.

    Legacy settings the new elements have no equivalent for are reported per migrated element:
    every non-empty FlexForm field besides the mode and the selections (``product_where``, ``myTS``,
    ``template_suffix`` …) and a starting point (:sql:`tt_content.pages`) whose folder did not
    become a category. A starting point whose folder did become a category is migrated into the
    element's category selection. A list restricted that
    way shows the whole catalog afterwards until it is rebuilt, typically as a category selection.

    The wizard also reads the TypoScript templates (:sql:`sys_template`) of the element's page and
    the pages above it, merged from the root down: the folders of ``plugin.tt_products.pid_list``
    become the categories of an element without a starting point of its own, and
    ``conf.tt_products.ALL.limit`` and ``conf.tt_products.ALL.orderBy`` (or ``limit`` and ``orderBy``
    directly below ``plugin.tt_products``) become the list's page size and sorting - ``title``,
    ``price``, ``price desc``, ``crdate desc``; ``uid`` keeps the default order. ``pid_list``
    folders without a category and other orders are reported. Constants and conditions are not
    evaluated.

    ..  warning::
        Run this wizard **before upgrading the site to TYPO3 v14.** TYPO3 v14 removes the
        :sql:`tt_content.list_type` column, and legacy ``tt_products`` never registered its
        plugins any other way — not even in its final v13 release. Once that column is gone,
        these elements can no longer be identified and must be rebuilt by hand.

#.  **Drop legacy tables** (``products_ttProductsLegacyCleanup``, optional) — a confirmable wizard
    that permanently drops the migrated ``tt_products``/``tt_products_cat``/``tt_products_articles``/
    ``sys_products_orders`` and visited-product tables (and their ``_language``/``mm`` siblings) once
    every wizard above reports nothing left to migrate. It refuses to run while any of them still has
    pending work, but it cannot verify media migration completeness automatically (see below) - run
    the media wizard first and confirm its output looks complete before confirming this one. Tables
    this extension never migrates at all (gifts, vouchers, the old graduated-price mechanism) are
    left untouched; remove them manually if no longer needed.

Known limitations
==================

*   **Variant, tag and free-text columns are not migrated, but reported.** The product text comes
    from ``note``, the purchase price from ``directcost``; the variant columns (``color``, ``size``,
    ``description``, ``material`` …), ``note2``, ``quality``, ``form`` and every other column
    carrying data without a counterpart here are listed by the product and article wizards with the
    number of affected records. Review that output before running the cleanup wizard, which drops
    the columns with their tables.

*   **Secondary thumbnails and slider images are not migrated.** Legacy ``smallimage`` (products,
    articles) and ``sliderimage`` (categories) are redundant pre-generated thumbnails of the main
    image; FAL generates thumbnails on demand, so only the main ``image`` (and, for products, the
    ``datasheet``) is migrated. The media wizard logs a notice for every legacy record that had one.
*   **Duplicate translations are resolved deterministically.** Legacy ``*_language`` tables have no
    uniqueness constraint on (parent, language). When duplicates exist, a non-hidden row always wins
    over a hidden one, and the highest uid wins among equally-visible candidates; the losing rows are
    reported, not migrated.
*   **Every migrated order uses the "invoice" payment method**, since it is the only payment method
    this extension implements. The wizard logs a warning for orders that actually used a different
    (electronic) payment gateway; the original gateway is preserved in the order's
    ``legacy_order_data`` field but is not re-creatable as a working payment method.
*   **Line item prices are not reconstructed from history.** The legacy ``orderData`` blob's price
    breakdown is a site- and version-specific serialized structure that cannot be parsed reliably, so
    migrated order line items use the *current* catalog price with an explanatory note instead of the
    price that was actually charged historically.
*   **Only one address per order.** The legacy schema only ever stored a single address; it becomes
    the order's billing address; no delivery address is created.
*   **Legacy country names are free text.** They are matched against TYPO3's country list where
    possible; anything that doesn't match is kept verbatim in the order's ``legacy_country_name``
    field instead of a resolved ISO country code.
