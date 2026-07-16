.. include:: /Includes.rst.txt

..  _start:

================================
Products Stripe Express Checkout
================================

:Extension key:
    products_express_stripe

:Package name:
    goldene-zeiten/products-express-stripe

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

Stripe's `Express Checkout Element <https://docs.stripe.com/elements/express-checkout-element>`__ as a
one-tap express checkout for the `Products <https://github.com/goldene-zeiten/products-core>`__ shop
system. A single button on the cart page surfaces Apple Pay, Google Pay, PayPal, Amazon Pay and Link — the
wallet supplies the address, shipping is quoted live from the shop's own carriers, and the payment is
settled and turned into a paid order without the shopper ever entering the multi-step checkout.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension adds to the shop, and how the button/wallet/confirm flow works.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        The Stripe publishable and secret keys, and placing the express button plugin.

    ..  card:: :ref:`Users Manual <users-manual>`

        What the shopper sees on the cart page.

    ..  card:: :ref:`Developer <developer>`

        The provider seam, the confirm endpoint and how the button JS is wired.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
