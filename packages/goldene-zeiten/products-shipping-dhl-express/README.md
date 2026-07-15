# TYPO3 extension `products_shipping_dhl_express`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Live DHL Express shipping rates for the
[Products](https://github.com/goldene-zeiten/products-core) shop system. At checkout the customer sees
real DHL Express products and prices, fetched from the DHL Express (MyDHL) Rating API through the
shop's existing shipping-provider seam. When DHL is unreachable or has no rate for a basket, the
shop's built-in table-rate shipping automatically takes over, so checkout never dead-ends.

This first release covers **rate quotes only**. Label printing and tracking are planned as a
separate, backend-side phase.

## Installation

```shell
composer require goldene-zeiten/products-shipping-dhl-express
```

Add the "Products DHL Express Shipping" site set to your site, then configure your MyDHL API credentials and
origin address (see the documentation).

## Documentation

- **Integrators / editors:** see `Documentation/` (rendered on the TYPO3 documentation site) —
  installation, configuration, the table-rate fallback, and the public extension points.
- **Contributors:** see [DEVELOPERS.md](DEVELOPERS.md) for the architecture, the rating internals, the
  shared HTTP layer, the WireMock mock server, and how the tests are structured.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core` and `goldene-zeiten/products-api-client`
- DHL Express (MyDHL API) credentials and account number

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
