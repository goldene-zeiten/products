.. include:: /Includes.rst.txt

..  _start:

=========================
Products PayPal Payment
=========================

:Extension key:
    products_payment_paypal

:Package name:
    goldene-zeiten/products-payment-paypal

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

PayPal Checkout as a payment method for the `Products <https://github.com/goldene-zeiten/products-core>`__
shop system: the customer is redirected to PayPal to approve payment, returns to the shop where the
order is captured server-to-server, and PayPal's webhook independently confirms the same capture.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension adds to the shop, and how the redirect/capture/webhook flow works.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        The PayPal REST app credentials and webhook settings.

    ..  card:: :ref:`Users Manual <users-manual>`

        What the shopper sees at checkout.

    ..  card:: :ref:`Developer <developer>`

        The public extension point for adjusting the outgoing PayPal order request.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
