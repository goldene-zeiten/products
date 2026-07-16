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

Activate the :guilabel:`Products Stripe Express Checkout` site set
(``goldene-zeiten/products-express-stripe``) on every site that should offer the express button, then adjust
its settings under :guilabel:`Site Management > Sites > Edit settings`.

Every setting below also has a system-wide default in the extension configuration
(:guilabel:`Admin Tools` → :guilabel:`Settings` → :guilabel:`Extension Configuration` →
:guilabel:`products_express_stripe`). A site setting left **empty inherits** that system-wide default; a
non-empty site setting **overrides** it for that one site — the same layered pattern the other Products API
integrations use. A single-site shop can therefore configure everything once in the extension configuration.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.express.stripe.publishableKey
        :type: string
        :Default: (empty)

        Stripe's publishable key (:code:`pk_test_...` / :code:`pk_live_...`). The browser renders the
        Express Checkout Element with it; it is safe to expose to the client. The button is not shown until
        this is set.

    ..  confval:: products.express.stripe.secretKey
        :type: string
        :Default: (empty)

        Stripe's secret key (:code:`sk_test_...` / :code:`sk_live_...`), used server-side to settle the
        wallet payment as a PaymentIntent. Stripe decides test vs. live from the prefix. The button is not
        shown until this is set.

    ..  confval:: products.express.stripe.apiBaseUrl
        :type: string
        :Default: (empty)

        Advanced. Overrides the Stripe API base URL — e.g. to route calls through a proxy, or to point the
        extension at a local mock server during development/testing. Leave empty to use Stripe's real host
        (``https://api.stripe.com``).

..  _configuration-plugin:

Placing the express button
==========================

The express button is a content element, :guilabel:`Products: Stripe Express Checkout`. Place it on the
**cart page**, typically above the :guilabel:`Proceed to checkout` button. It renders for the current
session basket and hides itself when the basket is empty, when the keys are not configured, or when the
basket currency is not supported by Stripe Express.

..  _configuration-confirm-page-type:

The confirm endpoint page type
==============================

The button JS posts the authorized wallet payment to a dedicated ``typeNum`` PAGE that runs only the confirm
action and returns its JSON verbatim. Its type number defaults to ``1784220800`` and can be changed via the
TypoScript constant :typoscript:`plugin.tx_productsexpressstripe.settings.confirm.pageType` if it collides
with another page type on the site. No route configuration is required; the endpoint is reachable on any page
of the site.
