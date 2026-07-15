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
*   EXT:products_core (``goldene-zeiten/products-core``) — the catalog this extension searches

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-search

Then activate the :guilabel:`Products Search` site set (``goldene-zeiten/products-search``) on the
site(s) that should offer search, and place the :guilabel:`Search` plugin (content element) on the
page(s) it should appear on. See :ref:`Configuration <configuration>` for the site setting and every
content-element field.
