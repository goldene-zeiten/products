:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14.3
*   PHP 8.2, 8.3, 8.4 or 8.5
*   The ``intl`` PHP extension

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products

Then activate the site set :guilabel:`Products` (``goldene-zeiten/products``) on the site(s) that
should show the shop, and configure the settings described under
:ref:`Configuration <configuration>` — most importantly the storage folder, since none of the
extension's records are organised by page.

If you already run an installation of the legacy ``tt_products`` extension, see
:ref:`Upgrading from tt_products <upgrading>` instead of setting up the catalog from scratch.
