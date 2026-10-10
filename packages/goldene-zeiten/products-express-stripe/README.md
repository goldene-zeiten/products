# TYPO3 extension `products_express_stripe`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Stripe's Express Checkout Element as a one-tap express checkout for the
[Products](https://github.com/goldene-zeiten/products-core) shop system. A single button on the cart page
offers Apple Pay, Google Pay, PayPal, Amazon Pay and Link — Stripe decides per browser and device which
wallets to show. The wallet supplies the shipping address, the shop quotes its own shipping live inside the
wallet sheet, and the paid order is created only after the payment has been settled for the amount the shop
computed itself.

It plugs into the core shop's express-checkout provider seam and reuses the Stripe account of
`products-payment-stripe`.

## Installation

```shell
composer require goldene-zeiten/products-express-stripe
```

Add the "Products Stripe Express Checkout" site set to your site, place the express checkout plugin on the
cart page and configure the Stripe publishable and secret keys — see the documentation. Without both keys no
button is shown.

## Documentation

See `Documentation/` (rendered on the TYPO3 documentation site) for installation, configuration, the express
flow and the public extension points.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core`, `goldene-zeiten/products-payment-stripe` and
  `goldene-zeiten/products-api-client`
- A Stripe account with a publishable and a secret API key

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
