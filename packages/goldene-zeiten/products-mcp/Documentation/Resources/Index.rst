:navigation-title: Resources

..  include:: /Includes.rst.txt
..  _resources:

=========
Resources
=========

An assistant reads resources to learn how the shop works. They are Markdown documents (``text/markdown``),
generated from the configuration of the site and rendered in the language of the site language the request
was resolved to.

..  _resources-list:

The resources
=============

``shop://guide/search``
    How to search: the fields the search matches (title, subtitle, item number, description, EAN), the category
    and manufacturer filters, and the page size when ``goldene-zeiten/products-search`` is installed.

``shop://guide/ordering``
    How to order: choosing a variant and a quantity, the basket. Personalised products and vouchers are only
    described when ``goldene-zeiten/products-personalization`` or
    ``goldene-zeiten/products-voucher`` is installed.

``shop://guide/checkout``
    The checkout steps (address, shipping method, payment method, review, place the order), the registered
    payment methods and shipping providers, the delivery countries (``products.checkout.allowedCountries``) and
    absolute links to the terms, privacy and withdrawal pages (``products.pids.*``).

``shop://shop/facts``
    Name, base URL, currency, whether prices include VAT (``products.pricing.mode``), the VAT ID and the trader
    identity (``products.withdrawal.trader.*``).

..  _resources-override:

Overriding a guide
==================

The guides are Fluid templates in ``Resources/Private/Templates/Guide/`` with the format ``md``. To replace one,
add a template root path to the site settings and place a template of the same name in it:

..  code-block:: yaml
    :caption: config/sites/shop/settings.yaml

    products:
      mcp:
        templateRootPaths:
          - 'EXT:products_mcp/Resources/Private/Templates/'
          - 'EXT:my_site/Resources/Private/Templates/Mcp/'

Every template gets the variables ``shopName`` and ``locale`` plus the ones the guide collects, for example
``paymentMethods``, ``countries`` and ``pages`` in the checkout guide. Translate texts with
``f:translate`` and ``languageKey: locale``. The texts of the shipped guides are in
``Resources/Private/Language/locallang_guide.xlf``.

An extension adds a guide by extending
:php:`GoldeneZeiten\Products\Mcp\Resource\AbstractGuideResource`.
