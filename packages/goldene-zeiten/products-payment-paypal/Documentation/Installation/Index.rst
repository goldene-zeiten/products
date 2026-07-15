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
*   A PayPal REST app — see :ref:`installation-paypal-app` below

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-payment-paypal

Then activate the site set :guilabel:`Products PayPal Payment`
(``goldene-zeiten/products-payment-paypal``) on every site that should offer PayPal at checkout, and
configure the settings described under :ref:`Configuration <configuration>` — at minimum the client
id and client secret, since PayPal is not offered at checkout until both are set.

..  _installation-paypal-app:

Getting a PayPal REST app
===========================

The client id and client secret come from a PayPal REST app, not from an ordinary PayPal account:

#.  Log in at `developer.paypal.com <https://developer.paypal.com/dashboard/>`__ with the PayPal
    account that should receive the payments.
#.  Under :guilabel:`Apps & Credentials`, create a new app (or use the default one). Start in the
    :guilabel:`Sandbox` tab for testing — it has its own separate client id/secret from
    :guilabel:`Live`.
#.  Copy the app's :guilabel:`Client ID` and :guilabel:`Secret` into
    `products.payment.paypal.clientId` / `products.payment.paypal.clientSecret`.
#.  Once ready to accept real payments, repeat this in the :guilabel:`Live` tab, set
    `products.payment.paypal.environment` to ``production``, and use the live credentials instead.

See :ref:`configuration-webhook` for the extra step of registering a webhook against the same app.
