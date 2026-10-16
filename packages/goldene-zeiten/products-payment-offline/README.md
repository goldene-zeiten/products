# Products Offline Payment

Offline payment methods for the [Products](https://github.com/goldene-zeiten/products-core) TYPO3 shop system, built
on its payment API like the invoice method of the core:

- **Prepayment** (bank transfer): the confirmation tells the customer what to transfer where; optional discount.
- **Cash on delivery**: the carrier collects the amount, with an optional fee; not offered for pick-up.
- **Cash on pick-up**: paid in store when collecting the order; only offered with pick-up; optional discount.

Discounts lower the price of the goods and are split across their VAT rates.

## Requirements

- TYPO3 13.4 or 14.3
- `goldene-zeiten/products-core`

## Installation

```bash
composer require goldene-zeiten/products-payment-offline
```

Add the site set "Products Offline Payment" and enable the methods in the site settings (category "Offline
payment"). Pick-up in store is provided by `goldene-zeiten/products-shipping-pickup`.

## Documentation

See the [manual](Documentation/Index.rst).
