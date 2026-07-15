# Developing `products_shipping_dhl_express`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is a single **shipping provider** plugged into the core shop's carrier seam.

- `Shipping/DhlExpressShippingProvider` implements the core `ShippingProviderInterface` (autoconfigured
  onto the `products.shipping_provider` tag) as a **non-fallback** provider with priority `100`, so it
  supersedes the built-in `TableRateShippingProvider`. Its `quote()` returns `[]` whenever it cannot serve
  the basket (unconfigured, DHL unreachable, error, or no rate), which is what lets the core registry fall
  back to the table-rate methods. `resolve()` re-quotes and matches the chosen product code.
- Everything the provider needs is resolved from the request-independent `DhlExpressConfiguration` value
  object, so the provider itself is free of settings and request handling.

Layered config resolution and the HTTP transport are **not** implemented in this package — they live in the
shared `goldene-zeiten/products-api-client` package and are only wired up here. That split is what lets a
future carrier/gateway package reuse the same config-layering and HTTP-client code instead of
reimplementing it. Unlike the UPS package, DHL Express needs no OAuth token provider at all: MyDHL API
authenticates every call with a plain HTTP Basic header, so there is no token cache, no token endpoint, and
no `Services.yaml` cache wiring to speak of.

## Configuration resolution

`Configuration/DhlExpressConfigurationFactory` is the only place in this package that reads configuration,
and even it does not read either source directly: it delegates the layering (system-wide extension
configuration under a site's settings, non-empty site setting wins, empty inherits) to the shared
`GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`, and resolves the current site via the
shared `CurrentSiteResolver`. `forCurrentRequest()` resolves the site from the current request; `forSite()`
takes a site explicitly (used in tests). The factory's own job is just mapping the resolver's flat
`array<string, string>` onto the typed `DhlExpressConfiguration` value object — parsing the environment
enum, uppercasing the country code, normalizing the weight unit (anything but `imperial` becomes `metric`),
splitting the product allow-list CSV, and trimming a trailing slash off `apiBaseUrl`.

`DhlExpressConfiguration::baseUrl()` returns the `apiBaseUrl` override when set, otherwise the
environment's real host (`DhlExpressEnvironment::baseUrl()`) — this is the seam the local mock and any
proxy use. `DhlExpressConfiguration::isComplete()` gates rating on `username`, `password`,
`originCountryCode` and `originCityName` all being non-empty; `accountNumber` and `originPostCode` are
**not** required for completeness (DHL happily rates without them, just less precisely) — anything missing
from the required set keeps the carrier silent so the table-rate fallback serves the basket.

## HTTP and authentication

`DhlExpressConfiguration::authorizationHeader()` builds the request's `Authorization` header directly:
`'Basic ' . base64_encode($username . ':' . $password)`. `Rating/HttpDhlExpressRatingClient` sends that
header on every call over the shared `GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient` — there is no
token acquisition step, no retry-on-401, and no dedicated cache, all of which the UPS package needs and
this one does not.

## Rating

`Rating/HttpDhlExpressRatingClient` (interface `DhlExpressRatingClient`, aliased via `#[AsAlias]`) builds
the query with `Rating/DhlExpressRateRequestBuilder`, dispatches `ModifyDhlExpressRateRequestEvent`, and
`GET`s `/rates` with the Basic auth header. Notable behaviour:

- The shop only tracks a delivery **country and postcode** for the basket, but DHL's rate request also
  wants a destination **city**. The builder sends the postcode as `destinationCityName` as well as
  `destinationPostalCode` — DHL geocodes by postcode + country regardless of what the city name says, so
  this does not affect the quoted rate. An integrator who needs a real city adjusts it via
  `ModifyDhlExpressRateRequestEvent`.
- No per-basket package dimensions exist in the shop either, so a small-parcel default (20 × 15 × 10, in
  the configured unit) is sent for every request; again, an integrator overrides this via the event.
- `isCustomsDeclarable` is derived, not configured: `true` whenever the resolved origin country differs
  from the basket's destination country.
- `plannedShippingDate` is always "tomorrow" (`now + 1 day`), formatted `Y-m-d`.
- Weight is converted from the basket's grams to the configured unit (kg or lb), with a `0.1` floor — DHL
  rejects a literal zero weight.
- HTTP 400 ("no products available for the requested lane") is a **business empty result**, returned as
  `[]`, not an error. Only transport failures (wrapped from the shared package's `ApiTransportException`)
  and any other non-200 status raise `DhlExpressRatingException`.
- A rated shipment's `products` array comes back either as a list or, for a single product, as a bare
  object — both are handled (`HttpDhlExpressRatingClient::mapRates()`).
- Each DHL product carries several `totalPrice` entries by `currencyType` (e.g. `BILLC`, `BASEC`).
  `billingPrice()` picks the `BILLC` ("billing currency") entry — the one the customer is actually
  charged — and only falls back to the first entry present if `BILLC` is missing.

The provider maps each `DhlExpressRate` to a core `ShippingOption` (label from the DHL `productName`,
falling back to `sprintf('DHL %s', $productCode)` when DHL sends an empty name; cost via
`Money::fromDecimalString`), filters by the `usedProducts` allow-list, and **skips any rate quoted in a
currency other than the basket's** so a foreign-currency amount is never presented as the shop's own. It
then dispatches `ModifyDhlExpressShippingOptionsEvent`. Unlike UPS, DHL Express product names arrive
ready-to-display from the API itself, so there is no separate service-catalog DI alias to override here.

## Extension points

`Event/ModifyDhlExpressRateRequestEvent` and `Event/ModifyDhlExpressShippingOptionsEvent` — documented for
integrators, with worked examples, in `Documentation/Developer/Index.rst`.

## The API mock

The HTTP tests run against the repo's **shared WireMock mock**, not a client double — one WireMock server
mocks every third-party API, so there is no per-API mock to build or publish. The DHL Express stubs live
under `Build/mocks/wiremock/mappings/shipping/dhl-express/rating/`, keyed on the destination country in the
outgoing query string:

- `rates-default.json` — matches everything not caught by a more specific stub; returns two products
  (`P` "EXPRESS WORLDWIDE" and `U` "ECONOMY SELECT") with `BILLC`/`EUR` prices. Used by e.g. destination
  `BE`.
- `rates-no-rate.json` — `destinationCountryCode=XX` → HTTP 400, DHL's "no products for this lane" answer.
- `rates-server-error.json` — `destinationCountryCode=YY` → HTTP 500.

DHL's real OpenAPI spec is pinned under `Build/mocks/specs/shipping/dhl-express/` as the reference. See
`Build/mocks/README.md`.

`runTests.sh -s functional` starts one `wiremock/wiremock` container with those mappings mounted, `waitFor`s
port 8080 (so tests never race it), and passes `MOCK_BASE_URL` to the test container, which reaches it by
name on the shared network. The extension is pointed at it purely through the `apiBaseUrl` override —
`MOCK_BASE_URL/shipping/dhl-express` — so no client change is needed. To run it by hand:

```shell
podman run --rm -p 8080:8080 -v "$PWD/Build/mocks/wiremock:/home/wiremock:ro" docker.io/wiremock/wiremock:3.10.0
```

Behaviour is **request-driven**: the rating stubs key on the destination country a test already controls
(`XX` → 400 no-rate, `YY` → 500, anything else, e.g. `BE` → the two-product default). A test selects a case
just by varying the destination it passes in.

## Testing

Every HTTP path is exercised against the real mock over HTTP — no client double.
`Tests/Functional/AbstractDhlExpressMockTestCase` extends the shared package's
`GoldeneZeiten\Products\Testing\AbstractApiMockTestCase` (from `packages-dev/products_testing`), which
skips the whole suite when `MOCK_BASE_URL` is unset (a plain phpunit run) and otherwise resets the mock's
scenario state and request journal per test, and exposes `recordedRequests()` / `loggedRequests()` against
WireMock's admin API. `AbstractDhlExpressMockTestCase` itself adds only what is DHL-specific: loading
`goldene-zeiten/products-api-client` and this extension, and a `configuration()` helper building a
`DhlExpressConfiguration` pointed at the mock.

- `Tests/Unit/Configuration/DhlExpressConfigurationFactoryTest` — the config layering and parsing, against
  the shared `ApiSettingsResolver` and `CurrentSiteResolver`. Uses a PHPUnit mock of
  `ExtensionConfiguration` (**not** an anonymous subclass — `ExtensionConfiguration` is `readonly` in
  TYPO3 v14, so subclassing it is a fatal there).
- `Tests/Functional/Rating/HttpDhlExpressRatingClientTest` — response mapping, the no-rate and server-error
  cases, and the outgoing `GET /rates` query shape (destination, weight, unit of measurement), asserted
  through WireMock's request journal.
- `Tests/Functional/Shipping/DhlExpressShippingProviderTest` — mapping, allow-list, currency guard, and the
  empty-result cases that yield to the fallback. Fakes the `DhlExpressRatingClient` *interface* (not a
  client), which is legitimate isolation from HTTP.

There is no OAuth-related test in this package at all — DHL Express's Basic-auth header is built inline in
`DhlExpressConfiguration::authorizationHeader()` and needs no token acquisition, caching or refresh logic to
verify.

## Planned: labels & tracking (phase 2)

Rating is synchronous and uses none of TYPO3's messaging stack. Labels and tracking are a separate,
backend-side phase, following the same shape already sketched for UPS:

- **Label generation** belongs on the TYPO3 message bus (Symfony Messenger) — dispatched asynchronously on
  order placement and consumed by `messenger:consume`, calling the DHL *Shipping* API out of the request
  cycle so it never blocks order confirmation.
- **Inbound tracking notifications** use TYPO3 **Reactions** (incoming webhooks), not the outgoing Webhooks
  system (which is fire-and-forget, TYPO3 → external, and returns no response).
