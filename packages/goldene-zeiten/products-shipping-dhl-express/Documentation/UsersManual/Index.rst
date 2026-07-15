:navigation-title: Users Manual

..  include:: /Includes.rst.txt
..  _users-manual:

=============
Users Manual
=============

This chapter is for editors and shop operators: what changes at checkout once DHL Express is configured.
See :ref:`Configuration <configuration>` for the technical settings.

..  contents:: Table of contents
    :local:

..  _users-manual-checkout:

DHL Express options at checkout
==================================

Once configured, the checkout's shipping-method step includes live DHL Express products alongside (and,
whenever DHL can serve the basket, ahead of) the shop's own table-rate shipping methods — for example
:guilabel:`EXPRESS WORLDWIDE` or :guilabel:`ECONOMY SELECT`, each with a real, live price and, where DHL
provides one, an estimated delivery date. Which products DHL offers, and at what price, depends on the
shipment's origin and destination and on the `products.shipping.dhlexpress.usedProducts` allow-list.

There is nothing to maintain per record for DHL Express itself: no shipping-method records, no manual
price list. The backend shipping-method records already maintained for the shop (storage folder record
list, per EXT:products_core's own "Shipping costs" chapter) keep working exactly as before — they simply
become the automatic fallback whenever DHL Express has nothing to offer for a given basket. See
:ref:`How rating behaves <configuration-how-rating-behaves>`.

..  _users-manual-fallback:

When DHL Express is not shown
================================

A customer sees no DHL Express options, and only the table-rate methods, whenever DHL is unconfigured,
unreachable, returns an error, or genuinely has no product for that shipment (an unsupported lane, for
instance). This is by design: an order nobody can ship must not become unpayable just because one carrier
had a problem. Nothing about this needs day-to-day editor attention — it self-heals the moment DHL is
reachable again — but see :ref:`Troubleshooting <configuration-troubleshooting>` if DHL options never
appear at all.

..  _users-manual-order-storage:

What shows up on an order
============================

When a customer chooses a DHL Express option, the order records it the same way any other carrier's choice
is recorded in EXT:products_core: a provider identifier (``dhl``), the chosen DHL product code as the
option identifier, and the human-readable label shown at checkout — so the backend order module and order
history keep showing the correct shipping method even if the extension is later uninstalled.
