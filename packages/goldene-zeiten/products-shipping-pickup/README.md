# Products Pick-up in Store

Pick-up in store for the [Products](https://github.com/goldene-zeiten/products-core) TYPO3 shop system. Every branch
is offered as a free shipping option of its own, named with its address, so customers choose where to collect their
order at checkout; the branch is stored on the order and named in the order mails.

The shipping provider identifier `pickup` lets payment methods depend on collection, e.g. cash on pick-up of
`goldene-zeiten/products-payment-offline`.

## Requirements

- TYPO3 13.4 or 14.3
- `goldene-zeiten/products-core` with shipping enabled

## Installation

```bash
composer require goldene-zeiten/products-shipping-pickup
```

Update the database schema and create a "Pick-up branch" record per branch.

## Documentation

See the [manual](Documentation/Index.rst).
