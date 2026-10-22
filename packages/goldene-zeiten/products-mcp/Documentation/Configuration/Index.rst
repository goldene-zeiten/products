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

Activate the :guilabel:`Products MCP` site set (``goldene-zeiten/products-mcp``) on every site that should serve
the endpoint, then adjust its settings under :guilabel:`Site Management > Sites > Edit settings`.

..  confval:: products.mcp.enabled
    :type: bool
    :Default: false

    Whether the site answers requests to the MCP endpoint. While off, the path is handled like any other URL of
    the site.

..  confval:: products.mcp.path
    :type: string
    :Default: /mcp

    Path of the endpoint, relative to the base of the site.

..  confval:: products.mcp.sessionLifetime
    :type: int
    :Default: 3600

    Seconds an MCP session stays valid without a request. Expired sessions are removed by the garbage collection
    that runs with a fraction of the requests.

..  confval:: products.mcp.templateRootPaths
    :type: stringlist
    :Default: EXT:products_mcp/Resources/Private/Templates/

    Template root paths of the guide resources. A later path overrides an earlier one, so a site replaces a guide
    by adding a path that contains ``Guide/Search.md`` (or ``Checkout.md``, ``Ordering.md``, ``Facts.md``).
    Start the template with ``{escapingEnabled=false}`` so that Markdown characters are not escaped.

..  confval:: products.mcp.partialRootPaths
    :type: stringlist
    :Default: EXT:products_mcp/Resources/Private/Partials/

    Partial root paths of the guide resources, used like the template root paths.

..  confval:: products.mcp.checkoutLinkLifetime
    :type: int
    :Default: 86400

    Seconds a checkout link stays valid, and how long an assistant basket is kept after its last change. The
    link is opened by the customer later than it is created, so it is generous by default; a basket is never
    kept for less than 60 seconds. Sites that share a database should use the same value, since expired baskets
    are removed for all of them.

..  confval:: products.mcp.rateLimit
    :type: int
    :Default: 120

    Requests per minute one client IP may send to the endpoint. A request over the limit is answered with
    ``429 Too Many Requests``, a JSON-RPC error body and a ``Retry-After`` header. ``0`` switches the limit off.
    The counters are kept in the caching framework (cache ``ratelimiter``), so they are shared by all PHP
    processes.

..  confval:: products.mcp.checkoutRateLimit
    :type: int
    :Default: 30

    Checkout links one client IP may open per minute, counted apart from the endpoint requests and answered the
    same way. ``0`` switches the limit off.

..  _configuration-tools:

Tools
=====

``shop_info``
    Returns the name, base URL and currency of the site the client is connected to.

The tools to search and describe the catalogue are listed under :ref:`tools`.

An extension adds a tool by implementing :php:`GoldeneZeiten\Products\Mcp\Registry\McpToolInterface`; the service
is picked up through autoconfiguration. Resources work the same way with
:php:`GoldeneZeiten\Products\Mcp\Registry\McpResourceInterface`.

..  _configuration-sessions:

Sessions
========

MCP sessions are stored in the table ``tx_productsmcp_session`` through TYPO3's session framework, under the
session identifier ``products_mcp`` in :php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['session']`. Point that identifier
at another session backend to keep the sessions elsewhere.

Baskets that assistants build are kept apart from the MCP sessions, in the table ``tx_productsmcp_basket`` under
the session identifier ``products_mcp_basket``, because the garbage collection of a session backend removes by
age across its whole table and a basket has to outlive the session.
