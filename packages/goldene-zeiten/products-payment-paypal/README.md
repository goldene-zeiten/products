# TYPO3 extension `products_payment_paypal`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

PayPal Checkout as a payment method for the
[Products](https://github.com/goldene-zeiten/products-core) shop system. At checkout the customer
can pay with PayPal: they are redirected to PayPal to approve, returned to the shop where the order
is captured, and PayPal's webhook confirms the same capture. It plugs into the shop's existing
payment-method seam, so it appears alongside invoice and any other configured method.

This first release covers the **pay flow** (create order, capture, webhook confirmation). Refunds
and cancellations from the backend are planned as a later phase.

## Installation

```shell
composer require goldene-zeiten/products-payment-paypal
```

Add the "Products PayPal Payment" site set to your site, then configure your PayPal REST app
credentials (client id, client secret) and, for webhook confirmation, your webhook id — see the
documentation.

## Documentation

- **Integrators / editors:** see `Documentation/` (rendered on the TYPO3 documentation site) —
  installation, configuration, the checkout flow, and the public extension point.
- **Contributors:** see [DEVELOPERS.md](DEVELOPERS.md) for the architecture, the create/capture and
  webhook internals, the shared OAuth/HTTP layer, the WireMock mock server, and how the tests are
  structured.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core` and `goldene-zeiten/products-api-client`
- A PayPal REST app (client id and secret) from [developer.paypal.com](https://developer.paypal.com)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
