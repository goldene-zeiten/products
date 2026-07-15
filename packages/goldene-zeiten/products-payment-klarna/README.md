# TYPO3 extension `products_payment_klarna`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Klarna as a payment method for the
[Products](https://github.com/goldene-zeiten/products-core) shop system, via Klarna's Hosted Payment
Page. At checkout the customer is redirected to Klarna to choose how to pay (pay now, pay later, pay in
instalments), returned to the shop where the order is placed, and Klarna's status callback confirms the
same session. It plugs into the shop's existing payment-method seam, so it appears alongside invoice,
PayPal, Stripe and any other configured method.

This first release covers the **pay flow** (open session, redirect, place order, status callback).
Refunds and cancellations from the backend are planned as a later phase.

## Installation

```shell
composer require goldene-zeiten/products-payment-klarna
```

Add the "Products Klarna Payment" site set to your site, then configure your Klarna API credentials
(username and API key) — see the documentation.

## Documentation

- **Integrators / editors:** see `Documentation/` (rendered on the TYPO3 documentation site) —
  installation, configuration, the checkout flow, and the public extension point.
- **Contributors:** see [DEVELOPERS.md](DEVELOPERS.md) for the architecture, the session/redirect/place-order
  and callback internals, the shared HTTP layer, the WireMock mock server, and how the tests are structured.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core` and `goldene-zeiten/products-api-client`
- Klarna API credentials from the [Klarna Merchant Portal](https://portal.klarna.com)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
