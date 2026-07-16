:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14.3
*   PHP 8.2, 8.3, 8.4 or 8.5
*   `goldene-zeiten/products-core` and `goldene-zeiten/products-api-client` (installed automatically)
*   An Apple Pay merchant identifier with a **registered merchant domain**, and a payment processor that
    can validate the Apple Pay merchant session and authorize the token — see :ref:`configuration`

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-express-apple-pay

Then:

#.  Activate the site set :guilabel:`Products Apple Pay Express Checkout`
    (``goldene-zeiten/products-express-apple-pay``) on every site that should offer the button.
#.  Configure the merchant identity and processor — see :ref:`Configuration <configuration>`.
#.  Place the :guilabel:`Products: Apple Pay Express Checkout` content element on the cart page.

..  _installation-domain:

Registering the merchant domain
===============================

Apple Pay on the web requires the checkout domain to be verified with Apple: host the
``apple-developer-merchantid-domain-association`` file Apple provides at
``https://<your-domain>/.well-known/apple-developer-merchantid-domain-association`` (served with no
redirect). Until the domain is verified, Apple will not validate the merchant session and the sheet will
not open.
