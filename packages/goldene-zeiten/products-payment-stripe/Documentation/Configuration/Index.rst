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

Activate the :guilabel:`Products Stripe Payment` site set (``goldene-zeiten/products-payment-stripe``)
on every site that should offer Stripe at checkout, then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings`.

Every setting below also has a system-wide default in the extension configuration
(:guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Extension Configuration` →
:guilabel:`products_payment_stripe`). A site setting left **empty inherits** that system-wide default; a
non-empty site setting **overrides** it for that one site — the same layered pattern used by the other
Products API integrations. This means a single-site shop can configure everything once in the extension
configuration and never touch the site settings at all.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.payment.stripe.secretKey
        :type: string
        :Default: (empty)

        Stripe's secret API key (:code:`sk_test_...` in test mode, :code:`sk_live_...` in live mode — see
        :ref:`installation-stripe-account`). Stripe itself decides test vs. live from this prefix; there
        is no separate environment setting. Stripe is not offered at checkout until this is set.

    ..  confval:: products.payment.stripe.webhookSecret
        :type: string
        :Default: (empty)

        The signing secret (:code:`whsec_...`) of the Stripe webhook endpoint, used to verify incoming
        events (see :ref:`configuration-webhook`). Required for the asynchronous webhook confirmation to
        ever succeed; without it, `handleWebhook()` always fails signature verification and payment
        confirmation relies on the customer's browser return alone.

    ..  confval:: products.payment.stripe.apiBaseUrl
        :type: string
        :Default: (empty)

        Advanced. Overrides the Stripe API base URL — e.g. to route calls through a proxy, or to point the
        extension at a local mock server during development/testing. Leave empty to use Stripe's real host
        (``https://api.stripe.com``).

..  _configuration-webhook:

Setting up the Stripe webhook
================================

The browser return alone tells the shop the customer came back from Stripe, but a customer who closes
the tab before returning, or whose browser drops the redirect, would otherwise leave the order stuck
pending. The webhook closes that gap: Stripe calls the shop's payment webhook independently of the
browser, so the order is still confirmed even if the return never happens.

#.  In the Stripe Dashboard, go to :guilabel:`Developers` → :guilabel:`Webhooks` → :guilabel:`Add
    endpoint`.
#.  Set the endpoint URL to the shop's fixed payment webhook endpoint:
    ``https://your-shop.example/products/payment/webhook`` (the same middleware path every redirect
    payment method in this shop shares — nothing Stripe-specific in the path itself).
#.  Select at least the :code:`checkout.session.completed` event to listen to; other events are
    acknowledged but ignored.
#.  Save the endpoint, then copy its :guilabel:`Signing secret` into
    `products.payment.stripe.webhookSecret`.

Without a matching webhook secret configured, `handleWebhook()` cannot verify any notification and
always reports failure — payment confirmation then depends entirely on the customer's browser
successfully returning to the shop. Test mode and live mode each have their own webhook endpoint and
signing secret in the Stripe Dashboard.

..  _configuration-payment-method-domains:

Registering the domain for Apple Pay, Google Pay and Wero
==============================================================

Card payments work out of the box once a secret key is configured. Apple Pay, Google Pay and Wero,
however, only appear on Stripe Checkout once the shop's checkout domain is registered with Stripe:

#.  In the Stripe Dashboard, go to :guilabel:`Settings` → :guilabel:`Payment methods` →
    :guilabel:`Payment method domains`.
#.  Add the domain the shop's checkout runs on (the domain the shopper is on before being redirected to
    Stripe Checkout).
#.  Repeat for both test mode and live mode domains if they differ (e.g. a staging domain and the
    production domain).

No further configuration is needed in this extension: once the domain is registered and the shopper's
device/browser supports a given wallet, Stripe Checkout offers it automatically alongside card.
