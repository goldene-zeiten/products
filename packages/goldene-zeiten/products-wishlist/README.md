# TYPO3 extension `products_wishlist`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

A wishlist for the [Products](https://github.com/goldene-zeiten/products-core) shop system: an
add-to-wishlist toggle on product listings and detail pages, and a wishlist page that lists, reorders and
removes saved products.

## Installation

```shell
composer require goldene-zeiten/products-wishlist
```

Add the "Products Wishlist" site set, place the Wishlist plugin, and enable `products.wishlist.enabled`.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
