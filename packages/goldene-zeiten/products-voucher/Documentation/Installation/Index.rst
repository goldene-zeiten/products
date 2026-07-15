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
*   ``goldene-zeiten/products-core`` ^1.0 (this extension is an add-on to it; it registers no
    functionality of its own without the core shop extension installed)

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-voucher

Then activate the :guilabel:`Products Voucher` site set (``goldene-zeiten/products-voucher``) on
every site that already has the :guilabel:`Products` site set active — the voucher site set declares
a dependency on it and will not do anything useful without it.

Once active, the voucher-code form appears on the basket page automatically; nothing further is
required for shoppers to redeem codes an editor has created (see :ref:`Editor <editor>`). To also
reward customers with an automatically-issued voucher on qualifying orders, see
:ref:`Configuration <configuration>`.
