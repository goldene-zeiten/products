:navigation-title: Upgrading from tt_products

..  include:: /Includes.rst.txt
..  _upgrading:

==========================
Upgrading from tt_products
==========================

If the legacy ``tt_products`` extension (and its tables) are still present in the installation,
six upgrade wizards under :guilabel:`Admin Tools > Upgrade` migrate its data into this extension's
tables. They are idempotent (safe to run more than once) and skip themselves entirely once the
legacy tables are gone.

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

Known limitations
==================

*   **Product images are not migrated.** They are out of scope for this migration; the wizard logs
    a notice for every legacy product that had one, so they can be re-uploaded manually.
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
