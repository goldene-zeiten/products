# TYPO3 extension `products`

A modern shop system for TYPO3, rewriting the legacy `tt_products` extension on top of
Extbase/Fluid: a category/product/article catalog, basket, guest-checkout-first payment flow, and
order history, plus upgrade wizards that migrate an existing `tt_products` installation in place.

## Requirements

- TYPO3 13.4 LTS or 14.3
- PHP 8.2, 8.3, 8.4 or 8.5
- The `intl` PHP extension

## Installation

```bash
composer require goldene-zeiten/products
```

Activate the `Products` site set on the site(s) that should show the shop and configure its
settings, most importantly the storage folder page, since none of this extension's records are
organised by page. See the [Configuration documentation](Documentation/Configuration/Index.rst)
for the full settings reference.

Already running `tt_products`? See the
[upgrade wizard documentation](Documentation/Upgrading/Index.rst) instead of setting up the
catalog from scratch.

## Documentation

Full documentation lives under [`Documentation/`](Documentation/Index.rst) and is rendered at
https://docs.typo3.org/ once published; render it locally with:

```bash
Build/Scripts/runTests.sh -s renderDocumentation
```

## Development

This extension uses TYPO3's standard `runTests.sh` container-based test runner. See `Build/Scripts/runTests.sh -h`
for all available suites (unit/functional tests, cgl, phpstan, linting, integrity checks).

```bash
# Run the full unit and functional test suites
Build/Scripts/runTests.sh -s unit
Build/Scripts/runTests.sh -s functional

# Check code style and static analysis
Build/Scripts/runTests.sh -s cgl
Build/Scripts/runTests.sh -s phpstan
```

### Acceptance tests

A Playwright suite drives a real demo shop (nested categories, search, basket, checkout, FE
login) end to end in a browser. It runs against a disposable TYPO3 instance that
`Tests/Acceptance/setupInstance.sh` builds fresh every time - nothing under
`Tests/Acceptance/Instance/` is ever committed.

```bash
Build/Scripts/runTests.sh -s acceptance -t 13 -d sqlite
Build/Scripts/runTests.sh -s acceptance -t 14 -d mariadb
```

The demo shop's page tree, category tree and products are hand-maintained CSV fixtures under
`Tests/Acceptance/Fixtures/` (the same multi-table format as `Tests/Functional/Fixtures/*.csv`),
imported via a small dev-only console command
(`Tests/Acceptance/Packages/dataset_import/`) that never ships as part of the extension itself.
