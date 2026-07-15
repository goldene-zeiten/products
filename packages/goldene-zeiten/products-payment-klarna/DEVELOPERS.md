# Developing `products_payment_klarna`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is a single **redirect payment method** plugged into the core shop's payment seam, built on
Klarna's **Hosted Payment Page** (HPP) rather than an embedded widget.

- `Payment/KlarnaPaymentMethod` implements the core `RedirectPaymentMethodInterface` (the same seam
  `InvoicePaymentMethod` and PayPal's method use via `PaymentMethodInterface`), registered automatically
  through the `#[AutoconfigureTag('products.payment_method')]` attribute on the interface — no manual
  `Services.yaml` entry. `isAvailable()` gates the offer on a complete configuration (username + password)
  and a currency Klarna settles in (`KlarnaPaymentMethod::SUPPORTED_CURRENCIES`), so an unconfigured shop
  never shows a broken option. Priority `40` ranks it above invoice (`0`).
- `initiate()` is a **two-step** open: it first opens a Klarna Payments session
  (`POST /payments/v1/sessions`), then wraps that session in an HPP session
  (`POST /hpp/v1/sessions`, `options.place_order_mode` = `NONE` so Klarna never places the order on its
  own) and returns `PaymentResult::redirectRequired()` with the HPP session's `redirect_url`.
- `handleReturn()` reads the `authorization_token` query parameter Klarna substitutes into the
  `merchant_urls.success` placeholder `{{authorization_token}}` this extension registers when creating the
  HPP session — not the shop's own signed callback token, which lives under the checkout plugin's own
  namespace, so the two never collide. It then places the order server-to-server
  (`POST /payments/v1/authorizations/{token}/order`) and interprets Klarna's `fraud_status`: `ACCEPTED` →
  paid, `PENDING` → pending, `REJECTED` → failed.
- `handleWebhook()` receives Klarna's asynchronous, **unsigned** `status_update` callback and never trusts
  its body directly — see "Webhook verification without a signature" below.
- Both callbacks are idempotent: `placeOrder()` short-circuits to `PaymentResult::completed(PaymentStatus::
  PAID)` without calling Klarna at all when the order is already `PaymentStatus::PAID` — a replayed return
  or a retried webhook after the order was already placed is a no-op either way.

## The single order line: `KlarnaOrderPayloadBuilder`

`Order/KlarnaOrderPayloadBuilder` is the one place that builds a Klarna order/cart payload (amounts in
**minor units**, `purchase_country`, `purchase_currency`, `order_lines`), and it is called both when
opening the payment session (`initiate()`) and when placing the order (`placeOrder()`). Klarna refuses to
place an order whose cart differs from the session it was opened for, so building both payloads from the
same code is what keeps them in lock step.

The builder deliberately produces a **single order line for the whole order total**, rather than
itemising the basket — the simplest payload that can never drift between the two calls. An integrator can
still itemise the *session* request through `ModifyKlarnaSessionRequestEvent` (see
`Documentation/Developer/Index.rst`), but that event only fires on session creation — the later
order-placement call is always rebuilt fresh from `KlarnaOrderPayloadBuilder` as a single line. A listener
that itemises the session without also itemising the order-placement side (which currently has no
extension point) risks a cart mismatch; the shipped default avoids the problem entirely by never
itemising either side.

## Webhook verification without a signature

Klarna's `status_update` callback carries only a session id and is **not signed** — there is no HMAC or
comparable header to check the way PayPal's transmission signature can be verified. Instead,
`KlarnaPaymentMethod::finalizeFromWebhook()` re-reads the HPP session straight from Klarna
(`GET /hpp/v1/sessions/{id}`), authenticated with the shop's own configured Basic credentials. A forged
callback body cannot make that authenticated re-read report a completed session with a valid
authorization token, so this re-read *is* the verification step — the same "verify with the gateway,
never trust the caller" rule the core Payment Methods API requires. Only a re-read status of `COMPLETED`
with a non-empty `authorization_token` proceeds to `placeOrder()`; anything else resolves to
`PaymentResult::pending()` (still `WAITING`) or `PaymentResult::failed()` (the re-read itself failed).

## Configuration resolution

`Configuration/KlarnaConfigurationFactory` is the only place that reads configuration. It delegates the
actual layering to the shared `GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`
(system-wide `ExtensionConfiguration` defaults, overridden field-by-field by a non-empty site setting
under `products.payment.klarna.*`) and maps the resolved array onto the immutable `KlarnaConfiguration`
value object. `forCurrentRequest()` resolves the site via the shared `CurrentSiteResolver`; `forSite()`
takes a site explicitly (used in tests, and by `KlarnaPaymentMethodTest`, which hand-assembles the factory
around a mocked `ExtensionConfiguration` instead of loading a real site). `KlarnaConfiguration::baseUrl()`
returns the `apiBaseUrl` override when set, otherwise `KlarnaEnvironment::baseUrl()` — this is the seam
the local mock (and any future proxy) uses. `isComplete()` (username + password both non-empty) is what
`KlarnaPaymentMethod::isAvailable()` gates on.

## Shared API infrastructure: Basic auth, no OAuth

Outbound HTTP calls go through `goldene-zeiten/products-api-client`'s `ApiHttpClient`, the same shared
HTTP wrapper UPS shipping and PayPal build on. Unlike PayPal, Klarna does **not** use OAuth: every call
carries a single `Authorization: Basic <base64(username:password)>` header, built by
`KlarnaConfiguration::authorizationHeader()` and attached by `HttpKlarnaClient` on every request. There is
no token grant/cache/renew cycle and no `OAuth2ClientCredentialsProvider`/token cache service to wire up —
`Configuration/Services.yaml` only auto-registers the classes under `Classes/*`, with no extra factory
entries needed.

`Client/KlarnaClient` is the interface (`createPaymentSession()`, `createHppSession()`,
`readHppSession()`, `placeOrder()`); `Client/HttpKlarnaClient` is the only implementation, aliased onto it
via `#[AsAlias(KlarnaClient::class)]`. Every non-2xx response, and every transport-level failure from
`ApiHttpClient`, is normalised into `Exception/KlarnaApiException` with a distinct exception code per call
site, which `KlarnaPaymentMethod` catches and turns into `PaymentResult::failed()` so the order stays
unpaid instead of the checkout breaking.

## The API mock

The HTTP tests run against the repo's **shared WireMock mock**, not a client double — see
`Build/mocks/README.md`. The Klarna stubs live under
`Build/mocks/wiremock/mappings/payment/klarna/` (`payments/`, `hpp/`, `authorizations/`), mirroring the
URL paths. Behaviour is **request-driven**, keyed on the session/authorization-token ids a test already
controls:

- `payments/create-session.json` — any create-session request succeeds, returning payment session id
  `kp_session_1`.
- `hpp/create-session.json` — any create-HPP-session request succeeds, returning HPP session id
  `hpp_session_1` with a `redirect_url` under `pay.playground.klarna.com`.
- `hpp/read-session-completed.json` — reading HPP session `hpp_session_1` returns `status: COMPLETED`
  with authorization token `auth_token_1`.
- `hpp/read-session-waiting.json` — reading HPP session `hpp_session_waiting` returns `status: WAITING`
  with no authorization token, exercising the "not yet completed" pending path.
- `authorizations/place-order-accepted.json` — placing an order against `auth_token_1` returns `200`
  with order id `klarna_order_1` and `fraud_status: ACCEPTED`.
- `authorizations/place-order-rejected.json` — placing an order against `auth_token_reject` returns
  `200` with order id `klarna_order_2` and `fraud_status: REJECTED`.
- `authorizations/place-order-not-found.json` — placing an order against `auth_token_bad` returns `404
  NOT_FOUND`, exercising `KlarnaApiException` from `placeOrder()`.

`runTests.sh -s functional` starts one `wiremock/wiremock` container with those mappings mounted,
`waitFor`s port 8080, and passes `MOCK_BASE_URL` to the test container. The extension is pointed at it
purely through `apiBaseUrl` = `MOCK_BASE_URL/payment/klarna` — no client change is needed. To run it by
hand:

```shell
podman run --rm -p 8080:8080 -v "$PWD/Build/mocks/wiremock:/home/wiremock:ro" docker.io/wiremock/wiremock:3.10.0
```

## Testing

Every HTTP path is exercised against the real mock over HTTP — no client double.
`Tests/Functional/AbstractKlarnaMockTestCase` (extending the shared `AbstractApiMockTestCase`) skips when
`MOCK_BASE_URL` is unset and assembles a `configuration()`/`client()` pointed at the mock.

- `Tests/Unit/Configuration/KlarnaConfigurationFactoryTest` — the config layering (extension defaults,
  site overrides, empty-inherits, `apiBaseUrl` override), using a PHPUnit mock of `ExtensionConfiguration`
  (**not** an anonymous subclass — it is `readonly` in TYPO3 v14, so subclassing it is a fatal there).
- `Tests/Functional/Client/HttpKlarnaClientTest` — opening a payment session, opening an HPP session,
  reading a completed/waiting session, placing an accepted/rejected order, and the unknown-authorization-
  token failure path (`KlarnaApiException` with code `1752600603`).
- `Tests/Functional/Payment/KlarnaPaymentMethodTest` — the full `KlarnaPaymentMethod` behaviour: initiate
  (including the missing-checkout-page failure), return-triggered order placement (including the
  already-paid no-op, verified against `recordedRequests()` on the place-order path, and the
  missing-authorization-token failure), and webhook handling (verified completed session, still-waiting
  session, missing session id).

## Planned: refunds and cancellations (phase 2)

This release implements only `RedirectPaymentMethodInterface` (open session, redirect, place order,
status-callback confirm). The core `RefundablePaymentMethodInterface` (`cancel()`, `refund()`) is not
implemented, so the backend order module offers no refund/cancel action for a Klarna-paid order yet. That
is planned as a later phase, calling Klarna's order management/refund API the same way `HttpKlarnaClient`
already calls session-open/order-place — reusing the same Basic-auth/HTTP/config-resolution layers, not a
parallel client.
