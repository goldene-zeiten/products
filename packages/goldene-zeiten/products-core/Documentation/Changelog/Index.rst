:navigation-title: Changelog

..  include:: /Includes.rst.txt
..  _changelog:

=========
Changelog
=========

..  contents:: Table of contents
    :local:

..  _changelog-2026-07-13-localizable-settings-labels:

2026-07-13 - Localizable site settings labels
==============================================

The category and setting labels in
:file:`Configuration/Sets/Products/settings.definitions.yaml` are no longer hardcoded English
strings. Every ``label:`` value is now an ``LLL:`` reference into a dedicated
:file:`Resources/Private/Language/locallang_settings.xlf` file, the same mechanism TYPO3 core uses
for its own Site Settings categories (for example
``LLL:EXT:backend/Resources/Private/Language/locallang_sitesettings.xlf:categories.other``).

..  code-block:: yaml
    :caption: Configuration/Sets/Products/settings.definitions.yaml (excerpt)

    categories:
      storage:
        label: 'LLL:EXT:products_core/Resources/Private/Language/locallang_settings.xlf:category.storage'
        parent: products

    settings:
      products.pids.storageFolder:
        type: int
        default: 0
        label: 'LLL:EXT:products_core/Resources/Private/Language/locallang_settings.xlf:setting.products.pids.storageFolder'
        category: storage

Label id convention
--------------------

:file:`locallang_settings.xlf` uses two id prefixes so categories and settings stay unambiguous
in one flat file:

*   ``category.<categoryKey>`` — e.g. ``category.storage``, ``category.pricing``.
*   ``setting.<fullSettingKey>`` — e.g. ``setting.products.pids.storageFolder``, using the
    setting's full dotted key from :file:`settings.definitions.yaml`.

Adding a translation
---------------------

Ship a translated copy of the file next to the original, prefixed with the target language's ISO
code, keeping every ``id``/``resname`` identical and only translating ``<source>``:

..  code-block:: xml
    :caption: Resources/Private/Language/de.locallang_settings.xlf (excerpt)

    <?xml version="1.0" encoding="utf-8" standalone="yes" ?>
    <xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
        <file source-language="en" target-language="de" datatype="plaintext"
              original="EXT:products_core/Resources/Private/Language/locallang_settings.xlf" product-name="products">
            <header/>
            <body>
                <trans-unit id="category.storage" resname="category.storage">
                    <source>Storage &amp; Pages</source>
                </trans-unit>
                <trans-unit id="setting.products.pids.storageFolder" resname="setting.products.pids.storageFolder">
                    <source>Speicherordner-UID</source>
                </trans-unit>
            </body>
        </file>
    </xliff>

TYPO3 core looks for this ``[iso-code].[filename].xlf`` file in the same directory as the source
file automatically — no registration is required. This is only supported directly in a locally
checked-out/composer-required copy of the extension; if you distribute the extension via the
TYPO3 Extension Repository (TER), translations are instead contributed and shipped through the
official `TYPO3 translation server <https://onlinehelp.typo3.org/>`__ (Crowdin) as a downloadable
language pack, following the same file layout convention.

..  note::
    Only the ``label`` value is localizable this way. Enum keys, defaults and the ``type``/
    ``category`` attributes in :file:`settings.definitions.yaml` are configuration, not
    user-facing text, and stay as plain values.
