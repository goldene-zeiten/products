# TYPO3 extension `products_voucher`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Vouchers and gift codes for the [Products](https://github.com/goldene-zeiten/products-core) shop system:
a voucher-code form on the basket, percentage/fixed/free-shipping discounts applied at checkout, and an
optional "gained voucher" that rewards a customer with a fresh code once an order passes a threshold.

## Installation

```shell
composer require goldene-zeiten/products-voucher
```

Add the "Products Voucher" site set. The voucher form appears on the basket automatically. To hand out
reward vouchers, enable `products.vouchers.gained.enabled`.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
