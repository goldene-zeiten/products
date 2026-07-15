:navigation-title: Installation

..  include:: /Includes.rst.txt
..  _installation:

============
Installation
============

..  _installation-requirements:

Requirements
============

*   TYPO3 13.4 LTS or 14.3
*   PHP 8.2, 8.3, 8.4 or 8.5
*   ``psr/http-client`` ^1.0 and ``psr/http-message`` ^1.1 || ^2.0 (both already required by TYPO3
    core)

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-api-client

..  note::
    In practice you rarely add this dependency directly. It is normally pulled in **transitively**
    as a dependency of an integration package such as ``goldene-zeiten/products-shipping-ups`` or
    ``goldene-zeiten/products-payment-paypal`` — Composer installs it automatically when you
    require one of those. Require it directly only when you are building a new integration package
    yourself.

There is nothing to activate: this library has no site set, no backend module and no settings of
its own. Once it is present in the installation, its three services are available for
autowiring — see :ref:`developer`.
