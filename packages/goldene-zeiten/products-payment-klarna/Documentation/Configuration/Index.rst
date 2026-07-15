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

Activate the :guilabel:`Products Klarna Payment` site set (``goldene-zeiten/products-payment-klarna``)
on every site that should offer Klarna at checkout, then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings`.

Every setting below also has a system-wide default in the extension configuration
(:guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Extension Configuration` →
:guilabel:`products_payment_klarna`). A site setting left **empty inherits** that system-wide default; a
non-empty site setting **overrides** it for that one site — the same layered pattern used by the other
Products API integrations. This means a single-site shop can configure everything once in the extension
configuration and never touch the site settings at all.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.payment.klarna.environment
        :type: string
        :Default: (empty, inherits ``playground``)

        Either ``playground`` (Klarna's test environment) or ``production``. Determines the Klarna API
        host calls go to (``api.playground.klarna.com`` / ``api.klarna.com``) unless
        `products.payment.klarna.apiBaseUrl` overrides it. Leave empty on a site to inherit the
        extension configuration's :guilabel:`Environment` (``playground`` by default). An unrecognised
        value also falls back to ``playground``.

    ..  confval:: products.payment.klarna.username
        :type: string
        :Default: (empty)

        The Klarna API credential username (the UUID shown next to the API key in the Merchant Portal —
        see :ref:`installation-klarna-credentials`). Klarna is not offered at checkout until both this
        and `products.payment.klarna.password` are set.

    ..  confval:: products.payment.klarna.password
        :type: string
        :Default: (empty)

        The Klarna API key (:code:`klarna_test_api_...` / :code:`klarna_live_api_...`). Prefer setting
        this in the system-wide extension configuration only (kept in the Install Tool, not in version
        control); a site setting is available for shops that genuinely need a different Klarna account
        per site.

    ..  confval:: products.payment.klarna.apiBaseUrl
        :type: string
        :Default: (empty)

        Advanced. Overrides the environment's Klarna API base URL — e.g. to route calls through a
        proxy, or to point the extension at a local mock server during development/testing. Leave empty
        to use `products.payment.klarna.environment`'s real Klarna host.

..  _configuration-webhook:

The status_update callback and how verification works
=========================================================

Unlike a classic webhook subscription, Klarna's asynchronous confirmation needs no separate
registration step: every Hosted Payment Page session this extension opens already carries the shop's
fixed payment webhook endpoint (``https://your-shop.example/products/payment/webhook`` — the same
middleware path every redirect payment method in this shop shares) as its :code:`merchant_urls.
status_update`. Klarna calls that URL on its own once the session's status changes, independently of
whether the customer's browser ever returns.

Klarna does **not** sign this callback the way PayPal signs its webhooks. There is nothing to configure
to make verification stronger — the extension always re-reads the session from Klarna itself using the
username/password configured above (see :ref:`introduction-webhook-verification`) rather than trusting
anything in the callback body. This means:

*   A correctly configured username/password pair is what makes webhook confirmation trustworthy at
    all — without valid credentials the re-read call itself fails, and the webhook is always reported
    as a verification failure rather than a payment.
*   There is no separate "webhook secret" or "webhook id" setting for Klarna, unlike PayPal — the same
    API credentials used for the session and order calls are also what authenticates the verification
    read.

Even without the callback ever arriving, the customer's browser return (`handleReturn()`) already
places the order and marks it paid on a successful authorization — the status callback exists to close
the gap for a customer who closes the tab before returning.
