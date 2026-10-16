# Products Basket Summary

A basket badge for the page header of the [Products](https://github.com/goldene-zeiten/products-core) TYPO3
shop system: a link to the basket with the number of items and the total.

The badge is rendered empty into cached pages and fills itself in the browser from the endpoint
`/products/basket-summary`, so the header never makes a page uncached. A server-rendered variant exists for
pages that are uncached anyway.

## Requirements

- TYPO3 13.4 or 14.3
- `goldene-zeiten/products-core`

## Installation

```bash
composer require goldene-zeiten/products-basket-summary
```

Add the site set "Products Basket Summary" (or the static template of the same name) and place the badge in
your page template:

```typoscript
page.10.variables.basket < lib.productsBasketSummary
```

`lib.productsBasketSummaryUncached` renders the badge on the server instead.

## Documentation

See the [manual](Documentation/Index.rst).
