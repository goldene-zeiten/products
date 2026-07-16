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

Activate the :guilabel:`Products Apple Pay Express Checkout` site set
(``goldene-zeiten/products-express-apple-pay``), then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings`. Every setting also has a system-wide default in the
extension configuration (:guilabel:`products_express_apple_pay`); an empty site setting inherits it, a
non-empty one overrides it for that site.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.express.applepay.merchantIdentifier
        :type: string
        :Default: (empty)

        The Apple Pay merchant identifier (:code:`merchant.com.example`) the payment request is made for.
        The button is not shown until this is set.

    ..  confval:: products.express.applepay.displayName
        :type: string
        :Default: (empty)

        The store name shown in the Apple Pay sheet.

    ..  confval:: products.express.applepay.countryCode
        :type: string
        :Default: (empty)

        The two-letter country the merchant is based in (e.g. :code:`DE`), required by the Apple Pay
        payment request.

    ..  confval:: products.express.applepay.apiBaseUrl
        :type: string
        :Default: (empty)

        Base URL of the payment processor that validates the merchant session and authorizes the token (see
        :ref:`developer-processor-contract`). The button is not shown until this is set.

    ..  confval:: products.express.applepay.apiKey
        :type: string
        :Default: (empty)

        Bearer credential the processor authorizes the requests with.

..  _configuration-plugin:

Placing the express button
==========================

The express button is a content element, :guilabel:`Products: Apple Pay Express Checkout`. Place it on the
**cart page**. It renders only in Safari/WebKit, for a non-empty basket, when the configuration is complete
and the basket currency is supported.

..  _configuration-page-types:

The endpoint page types
=======================

The Apple Pay JS posts to two dedicated ``typeNum`` PAGEs — merchant-validation and confirm. Their type
numbers default to ``1784220850`` / ``1784220851`` and can be changed via the TypoScript constants
:typoscript:`plugin.tx_productsexpressapplepay.settings.{validate,confirm}.pageType`.
