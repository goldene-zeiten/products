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
*   EXT:products_core (``goldene-zeiten/products-core``) ^1.0
*   Composer mode: the protocol layer is the Composer package ``mcp/sdk`` and its dependencies

..  _installation-composer:

Installation with Composer
===========================

..  code-block:: bash

    composer require goldene-zeiten/products-mcp

Composer asks to allow the ``php-http/discovery`` plugin, a dependency of ``mcp/sdk``. Allow it in the
``allow-plugins`` section of the project's ``composer.json``.

Then activate the :guilabel:`Products MCP` site set (``goldene-zeiten/products-mcp``) on the site that should
serve the endpoint and enable the :confval:`products.mcp.enabled <products.mcp.enabled>` setting.
