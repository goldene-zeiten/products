.. include:: /Includes.rst.txt

..  _start:

=============================
Products Amazon Pay Payment
=============================

:Extension key:
    products_payment_amazon

:Package name:
    goldene-zeiten/products-payment-amazon

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

Amazon Pay as a payment method for the `Products <https://github.com/goldene-zeiten/products-core>`__ shop
system, via Amazon Checkout v2: the customer is redirected to Amazon to authenticate and authorize the payment,
returns to the shop where the payment is reviewed and completed, and Amazon's status callback independently confirms
the same session. It plugs into the shop's existing payment-method seam, so it appears alongside invoice,
Klarna, PayPal and any other configured method.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension adds to the shop, and how the authentication/redirect/review/complete flow works.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        The Amazon Pay Seller Central credentials and region/sandbox settings.

    ..  card:: :ref:`Users Manual <users-manual>`

        What the shopper sees at checkout.

    ..  card:: :ref:`Developer <developer>`

        The public extension point for adjusting the outgoing Amazon Checkout Session request.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
