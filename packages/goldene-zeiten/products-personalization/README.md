# Products Personalization

Options a customer chooses when adding a product to the basket of the
[Products](https://github.com/goldene-zeiten/products-core) TYPO3 shop system: a ring size from a list, an engraving
as free text, each with an optional surcharge.

- Options are defined per product and can be added or replaced per article.
- Selection lists with a surcharge per choice; free texts limited in length and, if needed, to engravable characters.
- The same product with other choices is a basket line of its own; surcharges are part of the price and VAT.
- Basket, checkout review, order mails, invoice and order record list the chosen options.
- An option or choice marked as personalizing excludes the line from the right of withdrawal (Art. 16(c) Directive
  2011/83/EU).

## Requirements

- TYPO3 13.4 or 14.3
- `goldene-zeiten/products-core`

## Installation

```bash
composer require goldene-zeiten/products-personalization
```

Update the database schema and add the site set "Products Personalization" (or the static template of the same
name). Options are then defined on the tab "Personalization" of a product or article.

## Documentation

See the [manual](Documentation/Index.rst).
