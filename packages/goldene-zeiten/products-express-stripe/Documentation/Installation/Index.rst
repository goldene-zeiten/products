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
*   `goldene-zeiten/products-core` (the shop this express checkout plugs into),
    `goldene-zeiten/products-payment-stripe` and `goldene-zeiten/products-api-client` (installed
    automatically as dependencies)
*   A Stripe account with a publishable **and** a secret API key — see :ref:`installation-stripe-account`

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-express-stripe

Then:

#.  Activate the site set :guilabel:`Products Stripe Express Checkout`
    (``goldene-zeiten/products-express-stripe``) on every site that should offer the express button.
#.  Configure the publishable and secret keys — see :ref:`Configuration <configuration>`. Without both, no
    button is shown.
#.  Place the :guilabel:`Products: Stripe Express Checkout` content element on the cart page — see
    :ref:`configuration-plugin`.

..  _installation-stripe-account:

Getting the Stripe keys
=======================

#.  Log in (or sign up) at `dashboard.stripe.com <https://dashboard.stripe.com>`__.
#.  Start in **test mode** while integrating: under :guilabel:`Developers` → :guilabel:`API keys`, copy the
    :guilabel:`Publishable key` (:code:`pk_test_...`) into `products.express.stripe.publishableKey` and the
    :guilabel:`Secret key` (:code:`sk_test_...`) into `products.express.stripe.secretKey`.
#.  For Apple Pay and Google Pay to appear, register the shop's checkout domain under Stripe's
    `Payment Method Domains <https://docs.stripe.com/payments/payment-methods/pmd-registration>`__.
#.  Once ready for real payments, switch the dashboard to **live mode** and repeat with the live keys
    (:code:`pk_live_...` / :code:`sk_live_...`). Stripe decides test vs. live purely from the key prefix, so
    there is nothing else in this extension to switch over.
