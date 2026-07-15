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

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

----

Vouchers and gift codes for the Products shop system: a voucher-code form on the basket,
percentage/fixed/free-shipping discounts applied at checkout, and an optional "gained voucher" that
rewards a customer with a fresh code once an order passes a threshold.

----

What it does
============

Once installed, a voucher-code control appears on the basket page (through the core basket plugin), and
applied codes are discounted at checkout. A voucher may take a fixed amount off, a percentage, or waive
the shipping cost. Every redemption is recorded against its order, and the backend order view gains a
panel listing the codes redeemed and any voucher the order earned. With the gained-voucher feature
enabled, placing a qualifying order issues the customer a new voucher. Without this extension the core
checkout has no voucher concept at all.

The feature is reached only through the core's discount contract, so the core stays unaware that vouchers
exist: the applied codes travel in the voucher's own slice of the checkout state, which the discount
provider reads from the request itself.

Installation
============

..  code-block:: bash

    composer require goldene-zeiten/products-voucher

Add the :guilabel:`Products Voucher` site set to your site. The voucher form appears on the basket
automatically. To reward customers with a voucher when an order qualifies, enable
:confval:`products.vouchers.gained.enabled`.

Settings
========

..  confval:: products.vouchers.gained.enabled
    :type: bool
    :Default: false

    Issue a fresh voucher to the customer when an order qualifies.

..  confval:: products.vouchers.gained.minimumOrderValue
    :type: number
    :Default: 0.00

    The order value from which a gained voucher is issued.

..  confval:: products.vouchers.gained.rewardType
    :type: string
    :Default: fixed

    Whether the gained voucher is a ``fixed`` amount or a ``percentage``.

..  confval:: products.vouchers.gained.rewardValue
    :type: number
    :Default: 5.00

    The value of the gained voucher, read according to the reward type.
