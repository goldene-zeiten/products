.. include:: /Includes.rst.txt

..  _start:

===================================
Products Apple Pay Express Checkout
===================================

:Extension key:
    products_express_apple_pay

:Package name:
    goldene-zeiten/products-express-apple-pay

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

Apple Pay as a standalone one-tap express checkout for the
`Products <https://github.com/goldene-zeiten/products-core>`__ shop system — the raw Apple Pay JS
``ApplePaySession``, with no third-party wallet wrapper. An Apple Pay button on the cart page opens the
sheet, shipping is quoted live against the shop's own carriers, and the encrypted token is settled through
the shop's own payment processor. Offered on its own, for shops that want Apple Pay without routing through
Stripe or PayPal.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        What this extension adds, and how the validate/shipping/authorize flow works.

    ..  card:: :ref:`Installation <installation>`

        How to install and activate the extension.

    ..  card:: :ref:`Configuration <configuration>`

        The Apple Pay merchant identity and the processor endpoint.

    ..  card:: :ref:`Users Manual <users-manual>`

        What the shopper sees on the cart page.

    ..  card:: :ref:`Developer <developer>`

        The provider seam, the processor contract and the button JS.

**Table of Contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    UsersManual/Index
    Developer/Index
