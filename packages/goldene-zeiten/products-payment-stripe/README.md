# TYPO3 extension `products_payment_stripe`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Stripe Checkout as a payment method for the
[Products](https://github.com/goldene-zeiten/products-core) shop system. At checkout the customer can
pay by card (and any other method enabled on the Stripe account, e.g. Wero, iDEAL, Bancontact): they
are redirected to Stripe's hosted checkout, returned to the shop where the payment is confirmed, and
Stripe's webhook confirms the same session. It plugs into the shop's existing payment-method seam, so
it appears alongside invoice, PayPal and any other configured method.

Built on the official [`stripe/stripe-php`](https://github.com/stripe/stripe-php) SDK. This first
release covers the **pay flow** (create session, confirm, webhook). Refunds and cancellations from the
backend are planned as a later phase.

## Installation

```shell
composer require goldene-zeiten/products-payment-stripe
```

Add the "Products Stripe Payment" site set to your site, then configure your Stripe secret key and, for
webhook confirmation, your webhook signing secret — see the documentation.

## Documentation

- **Integrators / editors:** see `Documentation/` (rendered on the TYPO3 documentation site) —
  installation, configuration, the checkout flow, and the public extension point.
- **Contributors:** see [DEVELOPERS.md](DEVELOPERS.md) for the architecture, the create/confirm and
  webhook internals, the SDK base-URL seam, the WireMock mock server, and how the tests are structured.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core` and `goldene-zeiten/products-api-client`
- A Stripe account with a secret API key from [dashboard.stripe.com](https://dashboard.stripe.com)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
