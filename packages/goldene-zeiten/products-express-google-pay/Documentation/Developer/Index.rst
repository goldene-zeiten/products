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

:php:`GoldeneZeiten\Products\Express\GooglePay\Express\GooglePayExpressCheckoutProvider` implements the core
:php:`GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface`. It decides availability (config
complete, currency supported) and hands the frontend the environment, merchant info, tokenization gateway,
merchant country, amount, currency and the core shipping-quote endpoint path.

The endpoints
=============

:php:`GoldeneZeiten\Products\Express\GooglePay\Controller\ExpressCheckoutController` has two actions:
:php:`buttonAction()` (renders the button and issues the signed basket token) and :php:`confirmAction()`
(authorizes the token and creates the paid order). The confirm action runs as its own ``typeNum`` PAGE
(:ref:`configuration-page-type`) so the Google Pay JS receives raw JSON. Live shipping has no endpoint here
— the sheet's ``onPaymentDataChanged`` callback is answered client-side from the core shipping-quote
middleware, exactly as the Stripe express provider does. The orchestration lives in
:php:`GoldeneZeiten\Products\Express\GooglePay\Express\GooglePayExpressCheckoutService`.

..  _developer-processor-contract:

The processor contract
======================

:php:`GoldeneZeiten\Products\Express\GooglePay\Payment\GooglePayProcessorClient` calls one endpoint under the
configured processor base URL, authorized with the configured API key as a bearer token. Point the base URL
at your acquirer/PSP (or a thin adapter that speaks this contract):

*   :code:`POST {base}/googlepay/authorize` with :code:`{token, amount, currency, gatewayMerchantId}` —
    where ``token`` is the Google Pay ``paymentMethodData.tokenizationData.token`` string and ``amount`` is
    integer minor units — → returns :code:`{status, transactionId}`. A ``status`` of :code:`approved` marks
    the payment taken; anything else is treated as declined and no order is created.

Server-authoritative amount
===========================

The amount authorized is recomputed on the server from the shop's own basket and the chosen shipping option
immediately before the processor call — the same "recompute, never trust the client" rule the other express
providers follow.

..  _developer-testing:

Testing
=======

The Google Pay sheet cannot be driven in a headless browser, so there is no browser test. The settlement
path — token authorization and paid-order creation — is covered end-to-end by
:php:`GooglePayExpressCheckoutServiceTest` and :php:`GooglePayProcessorClientTest`, which run the real
processor HTTP call against the shared WireMock mock and assert a paid order is created (and that a declined
token creates none).
