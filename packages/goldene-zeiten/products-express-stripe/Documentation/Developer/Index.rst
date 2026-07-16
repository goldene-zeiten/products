:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

=========
Developer
=========

..  contents:: Table of contents
    :local:

The provider
============

:php:`GoldeneZeiten\Products\Express\Stripe\Express\StripeExpressCheckoutProvider` implements the core
:php:`GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface` and is registered by the tagged
provider registry. It decides availability (keys configured, supported currency) and hands the frontend the
static button configuration — the publishable key, the basket amount and currency, and the core shipping-quote
endpoint path. Everything else the frontend needs is added by the button plugin, since only it holds the live
basket.

The button plugin
=================

:php:`GoldeneZeiten\Products\Express\Stripe\Controller\ExpressCheckoutController` has two actions:

*   :php:`buttonAction()` renders the Express Checkout Element mount for the live session basket. It builds
    the :php:`ExpressCheckoutContext`, asks the provider whether it is available, and — when it is — issues
    the per-basket signed token via the core :php:`ExpressBasketFactory` and
    :php:`ExpressBasketTokenService`, then emits the button configuration, the token and the confirm URL into
    the mount element's data attributes.

*   :php:`confirmAction()` is the endpoint the wallet JS posts to. It reads the wallet address and chosen
    shipping option from the request body, recomputes the charge from the live basket, and delegates to
    :php:`GoldeneZeiten\Products\Express\Stripe\Express\StripeExpressConfirmService`, which settles the
    PaymentIntent and creates the paid order through the core :php:`ExpressOrderService`. It answers with a
    JSON ``{"redirectUrl": …}`` pointing at the checkout thank-you page.

The confirm endpoint runs as a dedicated ``typeNum`` PAGE (see :ref:`configuration-confirm-page-type`) so it
executes as a normal in-page request — with the session basket and full configuration available — yet returns
its JSON response verbatim rather than embedded in a rendered page.

The button JavaScript
=====================

:file:`Resources/Public/JavaScript/express-checkout.js` is a plain ES module mounted via
:html:`<f:asset.script type="module">`. It reads the mount element's data attributes, loads Stripe.js, mounts
the Express Checkout Element, and:

*   answers the element's :code:`shippingaddresschange` / :code:`shippingratechange` callbacks from the core
    shipping-quote endpoint, keeping the sheet total in step with the chosen option via
    :code:`elements.update()`;
*   on :code:`confirm`, creates a PaymentMethod from the wallet and posts it — with the address and chosen
    shipping option — to the confirm endpoint, then sends the browser to the returned thank-you URL.

..  _developer-testing:

Testing
=======

A real wallet sheet (Apple Pay / Google Pay) is a device-and-browser feature and cannot be driven in a
headless browser, so there is no browser acceptance test for the wallet UI itself. Instead the
settle-and-create path — the part that would break silently — is covered end-to-end by
:php:`StripeExpressConfirmServiceTest`, which runs the real Stripe SDK against the shared WireMock mock and
asserts a paid order is created (and that a declined card creates none). The core
:php:`ExpressBasketFactory` and its signed-token round-trip are covered by
:php:`ExpressBasketFactoryTest` in ``products-core``.
