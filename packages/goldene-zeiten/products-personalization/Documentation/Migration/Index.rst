.. include:: /Includes.rst.txt

..  _migration:

==========================
Migration from tt_products
==========================

tt_products offered choices such as ring sizes as selection-list columns of a product (``48;50;52`` in
``color``, ``size`` and their numbered siblings). Which column meant what was configured per installation,
so the columns to migrate are named in the extension configuration.

..  confval:: migration.optionColumns
    :name: migration-optionColumns
    :type: string
    :Default: (empty)

    The tt_products columns that become selection lists of the migrated products, comma-separated, each
    as ``column:Label``. A column becomes a required selection list of its values, split at ``;`` and line
    breaks; the column name is the option identifier.

..  confval:: migration.personalizingOptionColumns
    :name: migration-personalizingOptionColumns
    :type: string
    :Default: (empty)

    Option columns whose choices make the goods custom-made, e.g. ``size`` for a list of engraving fonts.
    Each choice of these columns is marked as personalizing, except the "none" values below.

..  confval:: migration.optionNoneValues
    :name: migration-optionNoneValues
    :type: string
    :Default: keine, ohne, none

    Choices meaning "no personalization", compared case-insensitively. They stay withdrawable.

..  confval:: migration.optionTextColumns
    :name: migration-optionTextColumns
    :type: string
    :Default: (empty)

    Adds an optional free text option next to the list of a column, e.g. ``size:Gravurtext`` for the
    engraving text tt_products had no column for. Every product with a value in the column gets it, with
    the identifier ``<column>_text``; it personalizes the goods, and its length is left open.

Example for a jewellery shop:

..  code-block:: text

    migration.optionColumns = color:Ringweite Damenring, color2:Ringweite Herrenring, color3:Oberfläche, size:Gravurschrift, size2:Schließe
    migration.personalizingOptionColumns = size
    migration.optionTextColumns = size:Gravurtext

Run the upgrade wizard :guilabel:`Turn tt_products selection lists into personalization options` after the
product migration of products_core. It only creates options a product does not have yet, so it can run
again after the configuration changed. Columns configured here are no longer reported as unmigrated by
the product migration.

Review the migrated options afterwards: surcharges, ring sizes that are made to measure (mark their choices
as personalizing) and the length of engraving texts cannot be read from tt_products. Customers of
tt_products often typed the engraving text into the order comment; migrated orders keep it there.

Legacy orders
=============

The order migration of products_core takes the lines of a tt_products order from its order data, where
the product row carries the values the customer chose. The columns of
:confval:`migration-optionColumns` become the options of the migrated line, so the order still names
the ordered size or engraving font; a choice of a :confval:`migration-personalizingOptionColumns`
column that is no "none" value marks the line as exempt from withdrawal.
