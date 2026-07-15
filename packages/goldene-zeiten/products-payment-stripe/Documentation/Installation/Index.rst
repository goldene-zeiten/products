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
*   `goldene-zeiten/products-core` (the shop this payment method plugs into) and
    `goldene-zeiten/products-api-client` (installed automatically as a dependency)
*   A Stripe account with a secret API key — see :ref:`installation-stripe-account` below

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-payment-stripe

Then activate the site set :guilabel:`Products Stripe Payment`
(``goldene-zeiten/products-payment-stripe``) on every site that should offer Stripe at checkout, and
configure the settings described under :ref:`Configuration <configuration>` — at minimum the secret key,
since Stripe is not offered at checkout until it is set.

..  _installation-stripe-account:

Getting a Stripe secret key
===========================

#.  Log in (or sign up) at `dashboard.stripe.com <https://dashboard.stripe.com>`__.
#.  Start in **test mode** while integrating: under :guilabel:`Developers` → :guilabel:`API keys`, copy
    the :guilabel:`Secret key` (it starts with :code:`sk_test_`) into
    `products.payment.stripe.secretKey`.
#.  Set up a webhook endpoint and copy its signing secret into
    `products.payment.stripe.webhookSecret` — see :ref:`configuration-webhook`.
#.  Once ready to accept real payments, switch the dashboard to **live mode** and repeat both steps with
    the live secret key (:code:`sk_live_...`) and a live-mode webhook. Stripe decides test vs. live purely
    from the secret key's prefix, so there is nothing else in this extension to switch over.

For Apple Pay, Google Pay and Wero to appear at checkout, also register the checkout domain — see
:ref:`configuration-payment-method-domains`.
