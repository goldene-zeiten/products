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

Activate the :guilabel:`Products PayPal Payment` site set (``goldene-zeiten/products-payment-paypal``)
on every site that should offer PayPal at checkout, then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings`.

Every setting below also has a system-wide default in the extension configuration
(:guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Extension Configuration` →
:guilabel:`products_payment_paypal`). A site setting left **empty inherits** that system-wide
default; a non-empty site setting **overrides** it for that one site — the same layered pattern
used by the other Products API integrations. This means a single-site shop can configure
everything once in the extension configuration and never touch the site settings at all.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.payment.paypal.environment
        :type: string
        :Default: (empty, inherits ``sandbox``)

        Either ``sandbox`` (PayPal's developer test environment) or ``production``. Determines the
        PayPal API host calls go to (``api-m.sandbox.paypal.com`` / ``api-m.paypal.com``) unless
        `products.payment.paypal.apiBaseUrl` overrides it. Leave empty on a site to inherit the
        extension configuration's :guilabel:`Environment` (``sandbox`` by default).

    ..  confval:: products.payment.paypal.clientId
        :type: string
        :Default: (empty)

        The PayPal REST app's client id (see :ref:`installation-paypal-app`). PayPal is not offered
        at checkout until both this and `products.payment.paypal.clientSecret` are set.

    ..  confval:: products.payment.paypal.clientSecret
        :type: string
        :Default: (empty)

        The PayPal REST app's client secret. Prefer setting this in the system-wide extension
        configuration only (kept in the Install Tool, not in version control); a site setting is
        available for shops that genuinely need a different PayPal app per site.

    ..  confval:: products.payment.paypal.webhookId
        :type: string
        :Default: (empty)

        The id of the PayPal webhook whose transmission signature incoming notifications are
        verified against (see :ref:`configuration-webhook`). Required for the asynchronous webhook
        confirmation to ever succeed; without it, `handleStaticWebhook()` always fails verification and
        payment confirmation relies on the customer's browser return alone.

    ..  confval:: products.payment.paypal.brandName
        :type: string
        :Default: (empty)

        Shown to the customer as the merchant name on the PayPal approval page. Leave empty to use
        the PayPal account's own default business name.

    ..  confval:: products.payment.paypal.apiBaseUrl
        :type: string
        :Default: (empty)

        Advanced. Overrides the environment's PayPal API base URL — e.g. to route calls through a
        proxy, or to point the extension at a local mock server during development/testing. Leave
        empty to use `products.payment.paypal.environment`'s real PayPal host.

..  _configuration-webhook:

Setting up the PayPal webhook
================================

The browser return alone tells the shop the customer came back from PayPal, but a customer who
closes the tab before returning, or whose browser drops the redirect, would otherwise leave the
order stuck pending. The webhook closes that gap: PayPal calls the shop's payment webhook
independently of the browser, so the order is still confirmed even if the return never happens.

#.  In the same PayPal app used for the client id/secret (`developer.paypal.com
    <https://developer.paypal.com/dashboard/>`__ → :guilabel:`Apps & Credentials` → your app), add a
    webhook.
#.  Set the webhook URL to the shop's PayPal webhook endpoint:
    ``https://your-shop.example/products/payment/webhook/paypal``. The trailing ``paypal`` segment
    identifies the gateway: because the PayPal webhook is configured once on the app rather than per
    order, the callback carries no per-order token, so the shop resolves the order from the signed event
    payload (the capture's ``custom_id``) and checks the captured amount against that order. The
    transmission signature is verified before that reference is trusted.
#.  Subscribe the webhook to at least the ``PAYMENT.CAPTURE.COMPLETED`` event. Subscribing to
    ``CHECKOUT.ORDER.APPROVED`` as well is harmless (it is only acknowledged, not acted on) but not
    required.
#.  Save the webhook, then copy its :guilabel:`Webhook ID` into
    `products.payment.paypal.webhookId`.

Without a matching webhook id configured, `handleStaticWebhook()` cannot verify any notification and
always reports failure — payment confirmation then depends entirely on the customer's browser
successfully returning to the shop.
