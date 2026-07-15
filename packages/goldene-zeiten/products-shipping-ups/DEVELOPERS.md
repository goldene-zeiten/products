# Developing `products_shipping_ups`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is a single **shipping provider** plugged into the core shop's carrier seam.

- `Shipping/UpsShippingProvider` implements the core `ShippingProviderInterface` (autoconfigured onto the
  `products.shipping_provider` tag) as a **non-fallback** provider with priority `100`, so it supersedes
  the built-in `TableRateShippingProvider`. Its `quote()` returns `[]` whenever it cannot serve the basket
  (unconfigured, UPS unreachable, error, or no rate), which is what lets the core registry fall back to the
  table-rate methods. `resolve()` re-quotes and matches the chosen service code.
- Everything the provider needs is resolved from the request-independent `UpsConfiguration` value object,
  so the provider itself is free of settings and request handling.

## Configuration resolution

`Configuration/UpsConfigurationFactory` is the only place that reads configuration. It layers the
`ExtensionConfiguration` (system-wide defaults) under the current site's settings: a non-empty site setting
overrides the extension default, an empty one inherits it. `forCurrentRequest()` resolves the site from
`$GLOBALS['TYPO3_REQUEST']`; `forSite()` takes a site explicitly (used in tests). `UpsConfiguration::baseUrl()`
returns the `apiBaseUrl` override when set, otherwise the environment's real host — this is the seam the
local mock and any proxy use.

## OAuth

`Authentication/UpsOAuthTokenProvider` performs the OAuth 2.0 client-credentials grant: `POST
/security/v1/oauth/token` with the credentials as an HTTP Basic header (not in the body) and
`grant_type=client_credentials` as a form body. Tokens are cached in the `products_shipping_ups_token`
cache and reused until shortly before they expire (renewed at 80% of `expires_in`, or on a 401 via the
`forceRefresh` path). The `expires_in` value is read dynamically — UPS shortened token lifetime from 4h to
1h in April 2026, so nothing about the duration is hardcoded.

The token cache is defined in `Configuration/Services.yaml` via a `CacheManager::getCache()` factory,
because TYPO3 only exposes `cache.*` DI services for its own built-in caches — a plain
`@cache.products_shipping_ups_token` reference fails to compile.

## Rating

`Rating/HttpUpsRatingClient` (interface `UpsRatingClient`) builds the request with
`Rating/UpsRateRequestBuilder`, dispatches `ModifyUpsRateRequestEvent`, and `POST`s to
`/api/rating/v2409/Shop` with a Bearer token. Notable behaviour:

- Weight-only body with postal code + country is enough for a Shop quote; no dimensions are sent (an
  integrator adds them via the event).
- A single rated shipment comes back from UPS as an object, several as a list — both are handled.
- HTTP 400 ("no rate available for this shipment") is a **business empty result**, returned as `[]`, not an
  error. Only transport failures and unexpected statuses raise `UpsRatingException`.
- A 401 triggers one retry with a freshly minted token.

The provider maps each `UpsRate` to a core `ShippingOption` (label from `UpsServiceCatalog`, cost via
`Money::fromDecimalString`), filters by the `usedServices` allow-list, and **skips any rate quoted in a
currency other than the basket's** so a foreign-currency amount is never presented as the shop's own. It
then dispatches `ModifyUpsShippingOptionsEvent`.

## Extension points

`Event/ModifyUpsRateRequestEvent`, `Event/ModifyUpsShippingOptionsEvent`, and the overridable
`Rating/UpsServiceCatalog` DI alias — documented for integrators in `Documentation/`.

## Local UPS mock

There is no official UPS mock server to wrap, so the repo ships one built from UPS's own OpenAPI specs.
Its **source lives in the monorepo** at `Build/mocks/ups-rating/` — a `Dockerfile` (Stoplight Prism with a
combined `spec/ups-mock.yaml` baked in) plus UPS's pinned `Rating.yaml` / `OAuthClientCredentials.yaml` as
the reference the combined spec is derived from. The `build-mock-images` GitHub workflow publishes it to
`ghcr.io/goldene-zeiten/products-ups-rating-mock`.

`Tests/Mock/docker-compose.yml` runs the published image; point the extension at it with the `apiBaseUrl`
override (`http://localhost:4010`). See `Tests/Mock/README.md`.

## Testing

The hermetic suite needs no network and no container: a `FakeHttpClient` (PSR-18) returns canned JSON.

- `Tests/Unit/Configuration/UpsConfigurationFactoryTest` — the config layering and parsing. Uses a PHPUnit
  mock of `ExtensionConfiguration` (**not** an anonymous subclass — `ExtensionConfiguration` is `readonly`
  in TYPO3 v14, so subclassing it is a fatal there).
- `Tests/Functional/Authentication/UpsOAuthTokenProviderTest` — token fetch, caching, force-refresh, error.
- `Tests/Functional/Rating/HttpUpsRatingClientTest` — request shape, response mapping (single & list),
  400-no-rate, 401-retry, transport errors.
- `Tests/Functional/Shipping/UpsShippingProviderTest` — mapping, allow-list, currency guard, and the
  empty-result cases that yield to the fallback.
- `Tests/Functional/Integration/UpsMockIntegrationTest` — drives the real Guzzle client against the mock
  over HTTP. **Skipped** unless `UPS_MOCK_BASE_URL` is set, so it never runs in the normal suite: no
  container dependency, no startup race. Start the mock and export that variable to run it.

## Planned: labels & tracking (phase 2)

Rating is synchronous and uses none of TYPO3's messaging stack. Labels and tracking are a separate,
backend-side phase:

- **Label generation** belongs on the TYPO3 message bus (Symfony Messenger) — dispatched asynchronously on
  order placement and consumed by `messenger:consume`, calling the UPS *Shipping* API out of the request
  cycle so it never blocks order confirmation.
- **Inbound tracking notifications** use TYPO3 **Reactions** (incoming webhooks), not the outgoing Webhooks
  system (which is fire-and-forget, TYPO3 → external, and returns no response).
