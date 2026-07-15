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
*   Klarna API credentials — see :ref:`installation-klarna-credentials` below

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-payment-klarna

Then activate the site set :guilabel:`Products Klarna Payment`
(``goldene-zeiten/products-payment-klarna``) on every site that should offer Klarna at checkout, and
configure the settings described under :ref:`Configuration <configuration>` — at minimum the API
username and password, since Klarna is not offered at checkout until both are set.

..  _installation-klarna-credentials:

Getting Klarna Merchant Portal credentials
=============================================

The API username and password come from a Klarna merchant account, not from an ordinary Klarna app
account:

#.  Log in at the `Klarna Merchant Portal <https://portal.klarna.com>`__ with the account that should
    receive the payments. Start in a **playground** account for testing — it has its own separate
    credentials from a live, **production** account.
#.  Under the API credentials section, create (or copy) a set of Klarna API credentials: an
    API username (a UUID shown next to the key) and an API password
    (:code:`klarna_test_api_...` in playground, :code:`klarna_live_api_...` in production).
#.  Copy the username and password into `products.payment.klarna.username` /
    `products.payment.klarna.password`.
#.  Once ready to accept real payments, repeat this with a production account's credentials, and set
    `products.payment.klarna.environment` to ``production``.

No separate webhook registration step is needed: Klarna's :code:`status_update` callback URL is sent
along with every Hosted Payment Page session this extension creates (see
:ref:`introduction-webhook-verification`), so there is nothing to configure in the Merchant Portal for
it.
