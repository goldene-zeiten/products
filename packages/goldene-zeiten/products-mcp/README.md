# TYPO3 extension `products_mcp`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

A [Model Context Protocol](https://modelcontextprotocol.io) endpoint for the
[Products](https://github.com/goldene-zeiten/products-core) shop system. AI assistants connect to a fixed path of
the site (default `/mcp`) and discover the shop's tools and resources. The protocol is served by the official
[`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk); MCP sessions are stored with TYPO3's session
framework.

This is an early version: it ships the endpoint, the registry for tools and resources, the session storage, one
demo tool (`shop_info`) and four resources generated from the shop configuration, in the site language and
overridable per site: the guides `shop://guide/search`, `shop://guide/ordering` and `shop://guide/checkout`, and
`shop://shop/facts`.

## Installation

```shell
composer require goldene-zeiten/products-mcp
```

Add the "Products MCP" site set to your site and enable the `products.mcp.enabled` setting.

## Documentation

See `Documentation/` (rendered on the TYPO3 documentation site) for installation and settings.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`
- Composer mode (the bundled-dependencies setup for classic installs is not available yet)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
