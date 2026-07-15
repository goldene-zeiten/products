.. include:: /Includes.rst.txt

..  _start:

=========================
Products Stripe Payment
=========================

:Extension key:
    products_payment_stripe

:Package name:
    goldene-zeiten/products-payment-stripe

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

Stripe Checkout as a payment method for the `Products <https://github.com/goldene-zeiten/products-core>`__
shop system: the customer is redirected to Stripe's hosted checkout to pay, returns to the shop where the
session is confirmed server-to-server, and Stripe's webhook independently confirms the same session. Card
payments and, wherever the shopper's device and Stripe account support them, Apple Pay, Google Pay and Wero
are all offered automatically, with no extra code or configuration in this extension.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension adds to the shop, and how the redirect/confirm/webhook flow works.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        The Stripe secret key and webhook settings.

    ..  card:: :ref:`Users Manual <users-manual>`

        What the shopper sees at checkout.

    ..  card:: :ref:`Developer <developer>`

        The public extension point for adjusting the outgoing Stripe Checkout Session request.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
