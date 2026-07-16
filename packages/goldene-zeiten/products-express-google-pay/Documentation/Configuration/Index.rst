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

Activate the :guilabel:`Products Google Pay Express Checkout` site set
(``goldene-zeiten/products-express-google-pay``), then adjust its settings under
:guilabel:`Site Management > Sites > Edit settings`. Every setting also has a system-wide default in the
extension configuration (:guilabel:`products_express_google_pay`); an empty site setting inherits it, a
non-empty one overrides it for that site.

..  confval-menu::
    :name: settings-overview
    :display: table
    :type:
    :Default:

    ..  confval:: products.express.googlepay.environment
        :type: string
        :Default: TEST

        :code:`TEST` uses Google's test cards; :code:`PRODUCTION` moves real money and needs an approved
        Google Pay merchant.

    ..  confval:: products.express.googlepay.merchantId
        :type: string
        :Default: (empty)

        The Google Pay merchant identifier (required in :code:`PRODUCTION`).

    ..  confval:: products.express.googlepay.merchantName
        :type: string
        :Default: (empty)

        The store name shown in the Google Pay sheet.

    ..  confval:: products.express.googlepay.countryCode
        :type: string
        :Default: (empty)

        The two-letter country the merchant is based in (e.g. :code:`DE`).

    ..  confval:: products.express.googlepay.gateway
        :type: string
        :Default: (empty)

        The Google Pay tokenization gateway id your processor uses (the ``PAYMENT_GATEWAY`` tokenization
        spec). The button is not shown until this is set.

    ..  confval:: products.express.googlepay.gatewayMerchantId
        :type: string
        :Default: (empty)

        Your merchant id at that gateway. The button is not shown until this is set.

    ..  confval:: products.express.googlepay.apiBaseUrl
        :type: string
        :Default: (empty)

        Base URL of the processor that authorizes the token (see :ref:`developer-processor-contract`). The
        button is not shown until this is set.

    ..  confval:: products.express.googlepay.apiKey
        :type: string
        :Default: (empty)

        Bearer credential the processor authorizes the requests with.

..  _configuration-plugin:

Placing the express button
==========================

The express button is a content element, :guilabel:`Products: Google Pay Express Checkout`. Place it on the
**cart page**. It renders where Google Pay is available, for a non-empty basket, when the configuration is
complete and the basket currency is supported.

..  _configuration-page-type:

The confirm endpoint page type
==============================

The Google Pay JS posts the authorized payment to a dedicated ``typeNum`` PAGE that returns its JSON
verbatim. Its type number defaults to ``1784220870`` and can be changed via the TypoScript constant
:typoscript:`plugin.tx_productsexpressgooglepay.settings.confirm.pageType`.
