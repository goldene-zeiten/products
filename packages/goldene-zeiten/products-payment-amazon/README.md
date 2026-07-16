# TYPO3 extension `products_payment_amazon`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Amazon Pay as a payment method for the
[Products](https://github.com/goldene-zeiten/products-core) shop system, via Amazon Checkout v2. At checkout
the customer is redirected to Amazon to authenticate, returns to the shop for a review-and-confirm page where
the final amount is verified, then returns again to complete the payment. The payment is confirmed independently
via an Amazon webhook callback. It plugs into the shop's existing payment-method seam, so it appears alongside
invoice, Klarna, PayPal, Stripe and any other configured method.

This first release covers the **pay flow** (create session, two-hop redirect with review, complete payment,
webhook confirmation). Refunds and cancellations from the backend are planned as a later phase.

## Installation

```shell
composer require goldene-zeiten/products-payment-amazon
```

Add the "Products Amazon Pay Payment" site set to your site, then configure your Amazon Pay API credentials
(public key ID, private key, store ID, and store name) — see the documentation.

## Documentation

- **Integrators / editors:** see `Documentation/` (rendered on the TYPO3 documentation site) —
  installation, configuration, the two-hop checkout flow, the webhook callback, and the public extension point.
- **Contributors:** see [DEVELOPERS.md](DEVELOPERS.md) for the architecture, the session/redirect/review/complete
  flow and webhook internals, request signing with RSASSA-PSS, the Amazon Checkout v2 API calls, the shared HTTP
  layer, the WireMock mock server, and how the tests are structured.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer
- `goldene-zeiten/products-core` and `goldene-zeiten/products-api-client`
- Amazon Pay Seller Central account and API credentials from the [Seller Central](https://sellercentral.amazon.com)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
