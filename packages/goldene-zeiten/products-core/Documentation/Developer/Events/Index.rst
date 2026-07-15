..  include:: /Includes.rst.txt
..  _developer-events:

======
Events
======

The Products extension exposes integration points through PSR-14 events that fire at critical
stages of the shop lifecycle. Integrators can listen to these events using attribute-based
registration with ``#[AsEventListener]`` to customize behavior — filter or reorder already-collected
options, modify data before it is persisted, or veto orders before creation.

**Understanding the Difference: Interfaces vs. Events**

The Products extension uses **interfaces for registration** and **events for post-filtering**:

- **Interfaces** (in ``Classes/``) are the mechanism to **add** new providers: implement
  :php:`PaymentMethodInterface` to add payment methods, :php:`OrderExportInterface` to add
  exporters, :php:`ShippingProviderInterface` to add carriers, etc. Interfaces are discovered
  and registered at DI container bootstrap via tagged service attributes.

- **Events** (PSR-14) fire **after** registration, carrying the collected list from the registry.
  Events let you reorder, hide, or filter what the registry already gathered — they cannot
  answer "which options are available", cannot be resolved by identifier, and cannot take part
  in order transactions. If you need to add a payment method, shipping carrier, or exporter,
  implement the interface, not an event listener.

For details on implementing each provider type, see :ref:`developer-api`.

**Table of Contents:**

..  toctree::
    :maxdepth: 1
    :titlesonly:

    Basket
    Catalog
    OrderAndCheckout
    Payment
    Shipping
    Invoice
    Voucher
    Export
