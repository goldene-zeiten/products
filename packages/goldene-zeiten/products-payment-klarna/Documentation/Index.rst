.. include:: /Includes.rst.txt

..  _start:

=========================
Products Klarna Payment
=========================

:Extension key:
    products_payment_klarna

:Package name:
    goldene-zeiten/products-payment-klarna

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

Klarna as a payment method for the `Products <https://github.com/goldene-zeiten/products-core>`__ shop
system, via Klarna's Hosted Payment Page: the customer is redirected to Klarna to choose how to pay,
returns to the shop where the order is placed server-to-server, and Klarna's status callback
independently confirms the same session.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension adds to the shop, and how the session/redirect/place-order flow works.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        The Klarna Merchant Portal credentials and environment settings.

    ..  card:: :ref:`Users Manual <users-manual>`

        What the shopper sees at checkout.

    ..  card:: :ref:`Developer <developer>`

        The public extension point for adjusting the outgoing Klarna session request.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
