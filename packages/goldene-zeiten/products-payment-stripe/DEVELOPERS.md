# Developing `products_payment_stripe`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is a single **redirect payment method** plugged into the core shop's payment seam.

- `Payment/StripePaymentMethod` implements the core `RedirectPaymentMethodInterface` (the same seam
  `InvoicePaymentMethod` uses via `PaymentMethodInterface`), registered automatically through the
  `#[AutoconfigureTag('products.payment_method')]` attribute on the interface — no manual `Services.yaml`
  entry. `isAvailable()` gates the offer on `StripeConfiguration::isComplete()` (a non-empty secret key)
  and a non-empty order currency, so an unconfigured shop never shows a broken option. Priority `50` ranks
  it above invoice (`0`); `calculateFee()` always returns `0`.
- `initiate()` creates a Stripe Checkout Session (`mode: payment`, one `line_items` entry for the order
  total, built by `StripePaymentMethod::buildSessionParameters()`) via the official `stripe/stripe-php`
  SDK and returns `PaymentResult::redirectRequired()` with `session.url`. `handleReturn()` reads the
  `session_id` query parameter Stripe substitutes into `success_url` (Stripe's own
  `{CHECKOUT_SESSION_ID}` placeholder — see `StripePaymentMethod::successUrl()`) and confirms it
  server-to-server by retrieving the session. `handleWebhook()` verifies the event via
  `\Stripe\Webhook::constructEvent()` and, on `checkout.session.completed`, interprets it the same way.
- Both callbacks are idempotent: `handleReturn()` short-circuits without calling Stripe at all when the
  order is already `PaymentStatus::PAID`, and `interpretSession()` (shared by both `confirm()` and
  `handleWebhook()`) is a pure mapping from `payment_status` (`paid`/`unpaid`/anything else) to a
  `PaymentResult` — safe to run more than once for the same session.

## Session create/confirm and the `api_base` seam

`Client/StripeClientFactory::create(StripeConfiguration $configuration)` is the only place a
`\Stripe\StripeClient` is built. It is per-request rather than a shared service because both its
`api_key` and `api_base` depend on the resolved site configuration:

- `api_key` is `StripeConfiguration::$secretKey`.
- `api_base` is `StripeConfiguration::baseUrl()` — the configured `apiBaseUrl` override when set,
  otherwise Stripe's real host `https://api.stripe.com`. This is the seam the local mock (and any future
  proxy) uses; nothing else about the client changes to point it at a mock.

`StripePaymentMethod` calls `checkout->sessions->create()` in `initiate()` and
`checkout->sessions->retrieve()` in `confirm()` (called only from `handleReturn()`, to look the session up
server-to-server from the bare `session_id` the browser came back with). Both catch
`\Stripe\Exception\ApiErrorException`, log it, and turn it into `PaymentResult::failed()` so the checkout
does not break on a Stripe-side error. `handleWebhook()` never calls the Stripe API at all: the event's
session object arrives embedded in the (locally, cryptographically verified) webhook body itself, so
`interpretSession()` reads `$event->data->object` directly instead of retrieving anything.

## Webhook verification

`handleWebhook()` never interprets the raw request body itself before verifying it: it passes the exact
raw body, the `Stripe-Signature` header, and the configured `webhookSecret` to
`\Stripe\Webhook::constructEvent()`, which does the HMAC verification inside the SDK and either returns a
trusted `\Stripe\Event` or throws (`\UnexpectedValueException` for a malformed payload,
`SignatureVerificationException` for a bad/expired signature — both caught and turned into
`PaymentResult::failed()`). Only after that does `interpretSession()` look at `$event->data->object`. An
empty `webhookSecret` still reaches `constructEvent()` and fails verification there — there is no separate
short-circuit, unlike PayPal's `webhookId` check.

## Configuration resolution

`Configuration/StripeConfigurationFactory` is the only place that reads configuration. It delegates the
actual layering to the shared `GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`
(system-wide `ExtensionConfiguration` defaults, overridden field-by-field by a non-empty site setting under
`products.payment.stripe.*`) and maps the resolved array onto the immutable `StripeConfiguration` value
object. `forCurrentRequest()` resolves the site via the shared `CurrentSiteResolver`; `forSite()` takes a
site explicitly (used in tests). Unlike PayPal, there is no separate environment enum: Stripe decides test
vs. live purely from the secret key's `sk_test_`/`sk_live_` prefix, so `StripeConfiguration` only carries
`secretKey`, `webhookSecret` and the optional `apiBaseUrl` override.

## The API mock

The HTTP tests run against the repo's **shared WireMock mock**, not a client double — see
`Build/mocks/README.md`. The Stripe stubs live under `Build/mocks/wiremock/mappings/payment/stripe/checkout/`,
mirroring the SDK's URL paths under `/payment/stripe/v1/checkout/sessions...`:

- `create-session.json` — any `POST /v1/checkout/sessions` succeeds, returning session id `cs_test_1`
  (`status: open`, `payment_status: unpaid`) with a `checkout.stripe.com` `url`.
- `retrieve-paid.json` — `GET /v1/checkout/sessions/cs_test_1` returns `status: complete`,
  `payment_status: paid`, `payment_intent: pi_test_1`.
- `retrieve-unpaid.json` — `GET /v1/checkout/sessions/cs_test_unpaid` returns `status: expired`,
  `payment_status: unpaid`, `payment_intent: null`.

`runTests.sh -s functional` starts one `wiremock/wiremock` container with those mappings mounted,
`waitFor`s port 8080, and passes `MOCK_BASE_URL` to the test container. The extension is pointed at it
purely through `apiBaseUrl` = `MOCK_BASE_URL/payment/stripe` — no client change is needed, since the
Stripe SDK's own `api_base` option is exactly that seam. To run it by hand:

```shell
podman run --rm -p 8080:8080 -v "$PWD/Build/mocks/wiremock:/home/wiremock:ro" docker.io/wiremock/wiremock:3.10.0
```

## Testing

Every HTTP path is exercised against the real mock over HTTP — no client double.
`Tests/Functional/AbstractStripeMockTestCase` (extending the shared `AbstractApiMockTestCase`) skips when
`MOCK_BASE_URL` is unset and assembles a `StripeConfiguration` pointed at
`$this->mockRoot . '/payment/stripe'` with the fixed test secret key `sk_test_x` and webhook secret
`whsec_test`.

- `Tests/Unit/Configuration/StripeConfigurationFactoryTest` — the config layering (extension defaults,
  site overrides, empty-inherits, `apiBaseUrl` override), using a PHPUnit mock of `ExtensionConfiguration`
  (**not** an anonymous subclass — it is `readonly` in TYPO3 v14, so subclassing it is a fatal there).
- `Tests/Functional/Payment/StripePaymentMethodTest` — the full `StripePaymentMethod` behaviour:
  `initiate()` (redirect required, and failure without a configured checkout page), `handleReturn()`
  against `cs_test_1` (paid) and `cs_test_unpaid` (unpaid), the already-paid-order no-op (asserted via the
  mock's request journal — zero `GET .../cs_test_1` calls), a missing `session_id` query parameter, and
  `handleWebhook()` with a correctly HMAC-signed `checkout.session.completed` body for `cs_test_1` versus
  an intentionally wrong signature. The webhook signature in the test is computed the same way Stripe's
  SDK verifies it (`t=<timestamp>,v1=<hmac-sha256(timestamp.payload, whsec_test)>`), not hand-waved.

## Planned: refunds and cancellations (phase 2)

This release implements only `RedirectPaymentMethodInterface` (create, confirm, webhook confirm). The
core `RefundablePaymentMethodInterface` (`cancel()`, `refund()`) is not implemented, so the backend order
module offers no refund/cancel action for a Stripe-paid order yet. That is planned as a later phase,
calling Stripe's `Refunds` API (`POST /v1/refunds`) through the same `StripeClientFactory`/
`StripeConfigurationFactory` layers `StripePaymentMethod` already uses — reusing the same seam, not a
parallel client.
