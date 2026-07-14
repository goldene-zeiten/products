# TYPO3 extension `products_core`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

The core of the Products shop system for TYPO3: catalog (categories, products,
articles, attributes), basket, checkout, orders, tax, pricing, and the backend
modules to manage them.

Shop features that not every installation needs — vouchers, credit points,
price tiers, wishlist, shipping, individual payment methods — ship as separate
add-on extensions that build on the extension points this package provides.

## Installation

```shell
composer require goldene-zeiten/products-core
```

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer

## Documentation

See the `Documentation/` directory, or the rendered documentation of the
monorepo.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
