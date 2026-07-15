# TYPO3 extension `products_api_client`

> **This repository is READ-ONLY.**
> It is split automatically out of the [goldene-zeiten/products](https://github.com/goldene-zeiten/products)
> monorepo, which is the single source of truth. Pull requests and commits made
> here are overwritten by the next split — please open them in the monorepo instead.

Shared OAuth2 client-credentials and PSR-18 HTTP plumbing used by the
[Products](https://github.com/goldene-zeiten/products-core) shop system's third-party API
integrations (shipping carriers, payment gateways). It has no shop concepts of its own — just the
token exchange, request building, and settings-layering that every REST integration would otherwise
copy.

You normally do not install this directly. It is pulled in as a dependency of an integration
package such as `goldene-zeiten/products-shipping-ups` or `goldene-zeiten/products-payment-paypal`.

## What it provides

- `ApiHttpClient` — a thin PSR-18 wrapper (`postJson`, `postForm`, `get`) that removes the
  stream/request/exception boilerplate and turns a transport failure into one exception type.
- `OAuth2ClientCredentialsProvider` + `OAuth2Credentials` — the client-credentials token flow with
  per-integration token caching (each consumer wires its own cache).
- `ApiSettingsResolver` + `CurrentSiteResolver` — resolve configuration by layering a system-wide
  extension configuration under a site's settings (empty site value inherits the default).

## Installation

```shell
composer require goldene-zeiten/products-api-client
```

There is no site set and no editor/integrator configuration of its own.

## Documentation

- **Developers:** see `Documentation/` for the public API and how a consuming package wires the
  token provider with its own cache.

## Requirements

- TYPO3 13.4 LTS or 14.3 LTS
- PHP 8.2 or newer

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
