..  include:: /Includes.rst.txt
..  _developer-events:

======
Events
======

The Products extension exposes integration points through PSR-14 events that fire at critical
stages of the shop lifecycle. Integrators can listen to these events using attribute-based
registration with ``#[AsEventListener]`` to customize behavior — add custom order processing,
filter payment methods, extend exports, or veto orders before creation.

**Table of Contents:**

..  toctree::
    :maxdepth: 1
    :titlesonly:

    Basket
    Catalog
    OrderAndCheckout
    Payment
    Invoice
    Voucher
    Export
