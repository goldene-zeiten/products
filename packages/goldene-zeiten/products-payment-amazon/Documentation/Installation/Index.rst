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
*   Amazon Pay Seller Central account and API credentials — see :ref:`installation-amazon-credentials` below

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-payment-amazon

Then activate the site set :guilabel:`Products Amazon Pay Payment`
(``goldene-zeiten/products-payment-amazon``) on every site that should offer Amazon Pay at checkout, and
configure the settings described under :ref:`Configuration <configuration>` — at minimum the public key ID,
private key, store ID and store name, since Amazon Pay is not offered at checkout until all are set.

..  _installation-amazon-credentials:

Getting Amazon Pay Seller Central credentials
==============================================

The API credentials come from an Amazon Pay merchant account:

#.  Log in at the `Amazon Pay Seller Central <https://sellercentral.amazon.com>`__ with the account that
    should receive the payments.
#.  Navigate to the Integration section and copy your Store ID (the value labeled "Merchant ID" or
    displayed as :code:`amzn1.application-oa2-client...`).
#.  In the same section, create or copy your API credentials: a Public Key ID
    (shown next to the key, e.g. :code:`SANDBOX-AB...` for testing, or :code:`LIVE-AB...` for live
    payments) and a Private Key (the RSA private key in PEM format).
#.  Copy the Public Key ID into `products.payment.amazon.publicKeyId`, the Private Key into
    `products.payment.amazon.privateKey`, and the Store ID into `products.payment.amazon.storeId`.
#.  Enter your shop name (displayed to buyers during checkout) in `products.payment.amazon.merchantStoreName`.
#.  By default, the extension calls Amazon Pay's **sandbox** (test) environment. Once ready to accept
    real payments, uncheck the `products.payment.amazon.sandbox` setting to switch to the live environment,
    and make sure the Public Key ID and Private Key come from your live Seller Central account.

The **private key** should be kept out of version control — store it in the Install Tool
(:guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Extension Configuration`) or reference
it via an environment variable so it never appears in git.
