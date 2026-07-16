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

:php:`GoldeneZeiten\Products\Express\ApplePay\Express\ApplePayExpressCheckoutProvider` implements the core
:php:`GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface`. It decides availability (config
complete, currency supported) and hands the frontend the merchant identifier, display name, merchant
country, amount, currency and the core shipping-quote endpoint path.

The endpoints
=============

:php:`GoldeneZeiten\Products\Express\ApplePay\Controller\ExpressCheckoutController` has three actions:
:php:`buttonAction()` (renders the button and issues the signed basket token), :php:`validateAction()`
(validates the merchant session through the processor), and :php:`confirmAction()` (authorizes the token
and creates the paid order). The two JSON actions run as their own ``typeNum`` PAGEs
(:ref:`configuration-page-types`) so the Apple Pay JS receives raw JSON. Live shipping has no endpoint here
— the sheet's shipping callbacks are answered client-side from the core shipping-quote middleware, exactly
as the Stripe express provider does. The orchestration lives in
:php:`GoldeneZeiten\Products\Express\ApplePay\Express\ApplePayExpressCheckoutService`.

..  _developer-processor-contract:

The processor contract
======================

:php:`GoldeneZeiten\Products\Express\ApplePay\Payment\ApplePayProcessorClient` calls two endpoints under the
configured processor base URL, authorized with the configured API key as a bearer token. Point the base URL
at your acquirer/PSP (or a thin adapter that speaks this contract):

*   :code:`POST {base}/applepay/merchant-validation` with
    :code:`{validationURL, merchantIdentifier, displayName, domainName}` → returns the Apple merchant
    session JSON (the processor holds the Apple Pay merchant certificate and performs the mutually
    authenticated call to Apple). HTTP 200 with the session object on success.

*   :code:`POST {base}/applepay/authorize` with :code:`{token, amount, currency, merchantIdentifier}` —
    where ``token`` is the ``ApplePayPaymentToken`` (its ``paymentData`` is the encrypted blob) and
    ``amount`` is integer minor units — → returns :code:`{status, transactionId}`. A ``status`` of
    :code:`approved` marks the payment taken; anything else is treated as declined and no order is created.

Server-authoritative amount
===========================

The amount authorized is recomputed on the server from the shop's own basket and the chosen shipping option
immediately before the processor call — the same "recompute, never trust the client" rule the other express
providers follow.

..  _developer-testing:

Testing
=======

The Apple Pay sheet is a native OS feature and cannot be driven in a headless browser, so there is no
browser test. The settlement path — merchant validation, token authorization and paid-order creation — is
covered end-to-end by :php:`ApplePayExpressCheckoutServiceTest` and :php:`ApplePayProcessorClientTest`,
which run the real processor HTTP calls against the shared WireMock mock and assert a paid order is created
(and that a declined token creates none).
