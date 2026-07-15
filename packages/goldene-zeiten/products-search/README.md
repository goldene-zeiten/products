# TYPO3 extension `products_search`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Product search and faceted browsing for the
[Products](https://github.com/goldene-zeiten/products-core) shop system: a search
plugin with free-text search plus browse modes (first letter, year, field value,
keyword multi-select, most recent), searching products, articles or categories.

## Installation

```shell
composer require goldene-zeiten/products-search
```

Add the "Products Search" site set to your site and place the Search plugin on a page.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
