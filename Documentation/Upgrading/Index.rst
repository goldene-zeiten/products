:navigation-title: Upgrading from tt_products

..  include:: /Includes.rst.txt
..  _upgrading:

==========================
Upgrading from tt_products
==========================

If the legacy ``tt_products`` extension (and its tables) are still present in the installation,
nine upgrade wizards under :guilabel:`Admin Tools > Upgrade` migrate its data into this
extension's tables. The migration wizards are idempotent (safe to run more than once) and skip
themselves entirely once the legacy tables are gone.

..  contents:: Table of contents
    :local:

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
    its ``tt_products_articles_language`` translations, linked to the migrated products.
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
