:navigation-title: Configuration

..  include:: /Includes.rst.txt
..  _configuration:

=============
Configuration
=============

..  contents:: Table of contents
    :local:

..  _configuration-site-set:

Site set
========

Activate the :guilabel:`Products Amazon Pay Payment` site set (``goldene-zeiten/products-payment-amazon``)
on every site that should offer Amazon Pay at checkout, then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings`.

Every setting below also has a system-wide default in the extension configuration
(:guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Extension Configuration` →
:guilabel:`products_payment_amazon`). A site setting left **empty inherits** that system-wide default; a
non-empty site setting **overrides** it for that one site — the same layered pattern used by the other
Products API integrations. This means a single-site shop can configure everything once in the extension
configuration and never touch the site settings at all.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.payment.amazon.region
        :type: string
        :Default: (empty, inherits ``eu``)

        Which Amazon Pay API region to call. One of:

        *   ``eu`` — European region (eu-west-1)
        *   ``na`` — North America region (us-east-1)
        *   ``jp`` — Japan region (ap-northeast-1)

        Leave empty on a site to inherit the extension configuration's :guilabel:`Region` (``eu`` by
        default). An unrecognised value also falls back to ``eu``.

    ..  confval:: products.payment.amazon.sandbox
        :type: bool
        :Default: checked (true)

        Whether to call Amazon Pay's sandbox (test) environment. Unchecked = live environment. The
        sandbox is useful for development and testing; once ready to accept real payments, uncheck
        this and ensure your API credentials come from your live Seller Central account.

    ..  confval:: products.payment.amazon.publicKeyId
        :type: string
        :Default: (empty)

        The Amazon Pay Public Key ID from Seller Central (shown in the Integration section, e.g.
        :code:`SANDBOX-AB...` for sandbox or :code:`LIVE-AB...` for live). Amazon Pay is not offered at
        checkout until this, `products.payment.amazon.privateKey`, `products.payment.amazon.storeId`,
        and `products.payment.amazon.merchantStoreName` are all set.

    ..  confval:: products.payment.amazon.privateKey
        :type: string
        :Default: (empty)

        The merchant RSA private key: either an absolute path to the PEM file (recommended) or the PEM
        contents inline. Prefer setting this in the system-wide extension configuration only (kept in
        the Install Tool, not in version control); a site setting is available for shops that genuinely
        need a different Amazon Pay account per site.

    ..  confval:: products.payment.amazon.storeId
        :type: string
        :Default: (empty)

        The Amazon Pay Store ID from Seller Central (shown in the Integration section as
        :code:`amzn1.application-oa2-client...`). Amazon Pay is not offered at checkout until this,
        `products.payment.amazon.publicKeyId`, `products.payment.amazon.privateKey`, and
        `products.payment.amazon.merchantStoreName` are all set.

    ..  confval:: products.payment.amazon.merchantStoreName
        :type: string
        :Default: (empty)

        The shop name shown to the buyer on Amazon's checkout pages (e.g. "My Shop"). Amazon Pay is not
        offered at checkout until this, `products.payment.amazon.publicKeyId`,
        `products.payment.amazon.privateKey`, and `products.payment.amazon.storeId` are all set.

    ..  confval:: products.payment.amazon.apiBaseUrl
        :type: string
        :Default: (empty)

        Advanced. Overrides the region's Amazon Pay API base URL — e.g. to route calls through a
        proxy, or to point the extension at a local mock server during development/testing. Leave empty
        to use `products.payment.amazon.region`'s real Amazon Pay host.

..  _configuration-signing:

Request signing
===============

Every outbound Amazon Pay API request is RSA-signed using the RSASSA-PSS algorithm and the configured
private key. The signature is computed by the extension using the official `amzn/amazon-pay-api-sdk-php`
library (signing only); the HTTP transport goes through the shared `goldene-zeiten/products-api-client`
package. There is no OAuth token exchange — the private key is the only credential needed to authenticate.

..  _configuration-webhook:

The webhook callback and how verification works
=================================================

Unlike Klarna (which does not sign its callbacks), Amazon Pay **signs every webhook callback** with a
digital signature. This extension verifies the signature using the Public Key ID and the AWS Signature
Version 4 signing process before trusting the notification. Only a successfully verified and COMPLETED
callback proceeds to mark the order paid.

The webhook callback URL is sent along with every Checkout Session this extension creates, and there is
no separate webhook registration step — Amazon can start sending callbacks as soon as the merchant
account is active.

Even without the callback ever arriving, the customer's browser return (`handleReturn()`, second leg)
already completes the payment and marks it paid on success — the webhook callback exists to close the
gap for a customer who closes the tab before returning.
