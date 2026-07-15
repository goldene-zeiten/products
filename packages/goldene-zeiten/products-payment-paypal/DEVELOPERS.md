# Developing `products_payment_paypal`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is a single **redirect payment method** plugged into the core shop's payment seam.

- `Payment/PaypalPaymentMethod` implements the core `RedirectPaymentMethodInterface` (the same seam
  `InvoicePaymentMethod` uses via `PaymentMethodInterface`), registered automatically through the
  `#[AutoconfigureTag('products.payment_method')]` attribute on the interface — no manual `Services.yaml`
  entry. `isAvailable()` gates the offer on a complete configuration (client id + secret) and a currency
  PayPal settles in (`PaypalPaymentMethod::SUPPORTED_CURRENCIES`), so an unconfigured shop never shows a
  broken option. Priority `50` ranks it above invoice (`0`).
- `initiate()` creates a PayPal Orders v2 order and returns `PaymentResult::redirectRequired()` with the
  approval URL. `handleReturn()` reads PayPal's bare `token` query parameter (not the shop's own signed
  callback token) and captures server-to-server. `handleWebhook()` verifies the transmission signature
  before interpreting the event body.
- Both callbacks are idempotent: `handleReturn()` short-circuits without calling PayPal at all when the
  order is already `PaymentStatus::PAID`, and `HttpPaypalOrderClient::capture()` treats a `422
  ORDER_ALREADY_CAPTURED` response as a successful capture rather than an error — a replayed return after
  the first one already finalized the order is a no-op either way.

## Create and capture: `HttpPaypalOrderClient`

`Order/PaypalOrderClient` is the interface (`createOrder()`, `capture()`); `Order/HttpPaypalOrderClient` is
the only implementation, aliased onto it via `#[AsAlias(PaypalOrderClient::class)]`. It:

- Builds the create-order payload via `Order/PaypalOrderRequestBuilder` (a single purchase unit for the
  order total, intent `CAPTURE`, `payment_source.paypal.experience_context` with the return/cancel URLs —
  empty when no checkout page is configured, letting PayPal fall back to the account's own default), then
  dispatches `ModifyPaypalOrderRequestEvent` so integrators can itemise the payload before it is sent. See
  `Documentation/Developer/Index.rst`.
- Retries once on a 401 (`authorizedPost()`): the cached OAuth token can be revoked early by PayPal, so a
  401 triggers a single retry with `$tokenProvider->getToken($credentials, true)` (forced refresh) before
  giving up.
- Maps `capture()`'s `201/200` response body to a `PaypalCapture` DTO, pulling the capture id out of
  `purchase_units[0].payments.captures[0].id`; anything else (a genuine decline, a transport failure) raises
  `PaypalApiException` with a distinct exception code per failure kind, which `PaypalPaymentMethod` catches
  and turns into `PaymentResult::failed()` so the order stays unpaid instead of the checkout breaking.

## Webhook verification: `PaypalWebhookVerifier`

`Webhook/PaypalWebhookVerifier` never interprets a webhook body itself — it only answers "did PayPal really
send this". It forwards the five `PayPal-*` transmission headers plus the decoded body and the configured
`webhookId` to PayPal's own `POST /v1/notifications/verify-webhook-signature`, and trusts the notification
only when that call returns `200` with `verification_status: SUCCESS`. An empty `webhookId` short-circuits
to "never trusted" before any HTTP call is made — there is nothing to verify against. Interpreting the
(now-trusted) event body into a `PaymentResult` is `PaypalPaymentMethod::interpretEvent()`'s job, not the
verifier's.

## Configuration resolution

`Configuration/PaypalConfigurationFactory` is the only place that reads configuration. It delegates the
actual layering to the shared `GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver` (system-
wide `ExtensionConfiguration` defaults, overridden field-by-field by a non-empty site setting under
`products.payment.paypal.*`) and maps the resolved array onto the immutable `PaypalConfiguration` value
object. `forCurrentRequest()` resolves the site via the shared `CurrentSiteResolver`; `forSite()` takes a
site explicitly (used in tests, and by `PaypalPaymentMethodTest`, which hand-assembles the factory around a
mocked `ExtensionConfiguration` instead of loading a real site). `PaypalConfiguration::baseUrl()` returns
the `apiBaseUrl` override when set, otherwise `PaypalEnvironment::baseUrl()` — this is the seam the local
mock (and any future proxy) uses. `isComplete()` (client id + secret both non-empty) is what
`PaypalPaymentMethod::isAvailable()` gates on.

## Shared API infrastructure

OAuth 2.0 client-credentials tokens and outbound HTTP calls go through
`goldene-zeiten/products-api-client`, the same package UPS shipping builds on: `OAuth2ClientCredentialsProvider`
handles the token grant/cache/renew cycle, `ApiHttpClient` wraps the actual HTTP calls. `Authentication/PaypalCredentialsFactory`
is the one place that turns a `PaypalConfiguration` into `OAuth2Credentials` (token endpoint
`/v1/oauth2/token` under the resolved base URL, client id/secret) — both `HttpPaypalOrderClient` and
`PaypalWebhookVerifier` build their credentials through it, so they never disagree on the token endpoint.

The token cache is defined in `Configuration/Services.yaml` via a `CacheManager::getCache()` factory (a
plain `@cache.products_payment_paypal_token` reference does not compile — TYPO3 only exposes `cache.*` DI
services for its own built-in caches), and the `OAuth2ClientCredentialsProvider` service is instantiated
with that cache explicitly so PayPal tokens never share storage with another integration's.

## The API mock

The HTTP tests run against the repo's **shared WireMock mock**, not a client double — see
`Build/mocks/README.md`. The PayPal stubs live under `Build/mocks/wiremock/mappings/payment/paypal/`
(`oauth/`, `orders/`, `webhook/`), mirroring the URL paths. Behaviour is **request-driven**, keyed on the
PayPal order id / body / webhook id a test already controls:

- `orders/create-default.json` — any create-order request succeeds, returning order id
  `PAYPAL-ORDER-1` (status `CREATED`) with a `payer-action` approval link.
- `orders/create-invalid-amount.json` — a create-order request whose `purchase_units[0].amount.value`
  is `0.00` returns `422 INVALID_CURRENCY_CODE`, exercising `PaypalApiException` from `createOrder()`.
- `orders/capture-completed.json` — capturing `PAYPAL-ORDER-1` returns `201 COMPLETED` with capture id
  `CAPTURE-1`.
- `orders/capture-declined.json` — capturing `PAYPAL-ORDER-DECLINE` returns `422 INSTRUMENT_DECLINED`.
- `orders/capture-already-captured.json` — capturing `PAYPAL-ORDER-CAPTURED` returns `422
  ORDER_ALREADY_CAPTURED`, exercising the "treat as success" idempotency path in `toCapture()`.
- `orders/capture-retry-1-unauthorized.json` + `orders/capture-retry-2-success.json` — capturing
  `PAYPAL-ORDER-RETRY` returns `401` on the first attempt and `201 COMPLETED` (capture id
  `CAPTURE-RETRY`) on the second, via a WireMock Scenario (`paypal-capture-retry`), exercising the
  401-then-retry path.
- `oauth/token-default.json` / `oauth/token-auth-fail.json` — any OAuth grant succeeds, except a
  request authenticated as client id `authfail` (Basic auth), which returns `401 invalid_client`.
- `webhook/verify-success.json` / `webhook/verify-failure.json` — a verify-signature request whose
  `webhook_id` is `WEBHOOK-OK` returns `verification_status: SUCCESS`; any other webhook id returns
  `FAILURE`.

`runTests.sh -s functional` starts one `wiremock/wiremock` container with those mappings mounted,
`waitFor`s port 8080, and passes `MOCK_BASE_URL` to the test container. The extension is pointed at it
purely through `apiBaseUrl` = `MOCK_BASE_URL/payment/paypal` — no client change is needed. To run it by
hand:

```shell
podman run --rm -p 8080:8080 -v "$PWD/Build/mocks/wiremock:/home/wiremock:ro" docker.io/wiremock/wiremock:3.10.0
```

## Testing

Every HTTP path is exercised against the real mock over HTTP — no client double.
`Tests/Functional/AbstractPaypalMockTestCase` (extending the shared `AbstractApiMockTestCase`) skips when
`MOCK_BASE_URL` is unset, flushes the `products_payment_paypal_token` cache per test, and assembles a
`configuration()`/`orderClient()`/`webhookVerifier()` pointed at the mock.

- `Tests/Unit/Configuration/PaypalConfigurationFactoryTest` — the config layering (extension defaults,
  site overrides, empty-inherits, `apiBaseUrl` override), using a PHPUnit mock of `ExtensionConfiguration`
  (**not** an anonymous subclass — it is `readonly` in TYPO3 v14, so subclassing it is a fatal there).
- `Tests/Functional/Order/HttpPaypalOrderClientTest` — order creation and payload shape (including the
  `ModifyPaypalOrderRequestEvent` dispatch), capture success/decline/already-captured/401-retry, and the
  create-order rejection path.
- `Tests/Functional/Payment/PaypalPaymentMethodTest` — the full `PaypalPaymentMethod` behaviour: initiate,
  return-triggered capture (including the already-paid no-op and the 401-retry), and webhook handling
  (verified capture-completed, unverified signature, invalid JSON body).

## Planned: refunds and cancellations (phase 2)

This release implements only `RedirectPaymentMethodInterface` (create, capture, webhook confirm). The core
`RefundablePaymentMethodInterface` (`cancel()`, `refund()`) is not implemented, so the backend order module
offers no refund/cancel action for a PayPal-paid order yet. That is planned as a later phase, calling
PayPal's Captures refund API (`POST /v2/payments/captures/{id}/refund`) the same way `HttpPaypalOrderClient`
already calls create/capture — reusing the same OAuth/HTTP/config-resolution layers, not a parallel client.
