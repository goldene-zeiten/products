..  include:: /Includes.rst.txt

..  _start:

================
Products Voucher
================

:Extension key:
    products_voucher

:Package name:
    goldene-zeiten/products-voucher

:Version:
    |release|

:Language:
    en

:Author:
    Markus Hofmann

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

Vouchers and gift codes for the EXT:products shop system: a voucher-code form on the basket, a
percentage, fixed-amount or free-shipping discount applied at checkout, and an optional "gained
voucher" that rewards a customer with a fresh code once an order passes a threshold. The feature is
reached only through the core's discount contract, so EXT:products (core) itself stays unaware that
vouchers exist.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension provides: voucher codes, free shipping and gained bonus vouchers.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        Site settings for the gained-voucher reward.

    ..  card:: :ref:`Editor <editor>`

        Creating voucher records, applying a code in the basket, and the backend redemption panel.

    ..  card:: :ref:`Developer <developer>`

        How the voucher feature plugs into the core's discount seam.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Editor/Index
    Developer/Index
