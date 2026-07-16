# Developing `products_payment_amazon`

Internal notes for contributors. User-facing documentation (integrators, editors, extension points) lives
in `Documentation/`; this file is about how the extension works and how to work on it.

## Architecture

The extension is a single **redirect payment method** plugged into the core shop's payment seam, built on
Amazon Checkout v2 with a **two-hop redirect** flow.

- `Payment/AmazonPayPaymentMethod` implements the core `RedirectPaymentMethodInterface` (the same seam
  `InvoicePaymentMethod`, Klarna and PayPal use), registered automatically through the
  `#[AutoconfigureTag('products.payment_method')]` attribute on the interface — no manual `Services.yaml`
  entry. `isAvailable()` gates the offer on a complete configuration (public key ID, private key, store
  ID, and store name) and a currency Amazon settles in, so an unconfigured shop never shows a broken
  option. Priority `30` ranks it above invoice (`0`) but below some premium methods.
- `initiate()` creates an Amazon Checkout Session (:code:`POST /checkout/sessions`) and returns
  `PaymentResult::redirectRequired()` with the session's checkout URL, sending the buyer to Amazon's
  authentication page.
- `handleReturn()` runs on the **first return** from Amazon and implements the two-hop flow: it reads the
  session (:code:`GET /checkout/sessions/{id}`) to verify the buyer's address, updates the session with
  the final order amount (:code:`PATCH /checkout/sessions/{id}`), and redirects back to Amazon
  (:code:`redirect=AmznCheckout`) so the buyer reviews and completes the payment.
- On the **second return** (distinguished by a `leg` query parameter), `handleReturn()` completes the
  payment (:code:`POST /checkout/sessions/{id}/complete-checkout-session`), interprets the charge status
  (COMPLETED → paid, PENDING → pending, FAILED → failed), and shows the order confirmation.
- `handleWebhook()` receives Amazon's asynchronous, **signed** webhook callback and verifies the signature
  before trusting it — see "Webhook verification with signature" below.
- Both callbacks are idempotent: if the order is already paid, the webhook handler or second return leg
  verifies the charge is complete before re-marking it paid.

## The configuration value object and factory: `AmazonPayConfiguration`

`Configuration/AmazonPayConfigurationFactory` is the only place that reads configuration. It delegates
the actual layering to the shared `GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver`
(system-wide `ExtensionConfiguration` defaults, overridden field-by-field by a non-empty site setting
under `products.payment.amazon.*`) and maps the resolved array onto the immutable `AmazonPayConfiguration`
value object. The configuration includes:

- `region` — one of `AmazonPayRegion` enum cases (EU, NA, JP), defaulting to EU
- `sandbox` — boolean, defaulting to true
- `publicKeyId` — the Public Key ID from Seller Central
- `privateKey` — the RSA private key (PEM format)
- `storeId` — the Store ID from Seller Central
- `merchantStoreName` — the shop name shown to buyers
- `apiBaseUrl` — optional override of the API host

`forCurrentRequest()` resolves the site via the shared `CurrentSiteResolver`; `forSite()` takes a site
explicitly (used in tests). `baseUrl()` returns the `apiBaseUrl` override when set, otherwise
`AmazonPayRegion::apiHost()` — this is the seam the local mock (and any future proxy) uses.
`isComplete()` (all credentials non-empty) is what `AmazonPayPaymentMethod::isAvailable()` gates on.

## Request signing: RSASSA-PSS via the official SDK

Every outbound HTTP request is RSA-signed using the RSASSA-PSS algorithm and the configured private key,
per Amazon Pay's authentication scheme. `Signing/AmazonPaySigner` wraps the official
`amzn/amazon-pay-api-sdk-php` library for signing only — HTTP transport and session management go through
the shared `goldene-zeiten/products-api-client` package. The signer computes the signature and
authorization header, which `HttpAmazonPayClient` attaches to every outgoing request.

There is no OAuth token exchange — the private key is the only credential needed.

## The two-hop redirect flow

The Amazon Checkout v2 spec requires a two-step buyer interaction:

1. **First leg (review and confirm):** `initiate()` creates the session and redirects to Amazon's checkout
   URL. The buyer authenticates and returns to the shop's review page (identified by a `leg=1` query
   parameter or absent `leg`).
2. **Review page:** The shop displays the buyer's address (fetched by re-reading the session), may update
   the order total if shipping cost changed, and updates the session with the final amount.
3. **Second leg (complete):** The shop redirects back to Amazon (with `redirect=AmznCheckout`) so the
   buyer reviews their payment method and confirms. Amazon returns again (with `leg=2`).
4. **Complete:** `handleReturn()` completes the session on the second return, marks the order paid if the
   charge succeeded, and shows the confirmation page.

The `leg` query parameter distinguishes the two returns so a single return URL can serve both.

## Webhook verification with signature

Amazon Pay **signs every webhook callback** with a digital signature computed using AWS Signature Version
4. `AmazonPayPaymentMethod::finalizeFromWebhook()` verifies the signature using the configured public key
ID and the same signing method before trusting the notification. Only a successfully verified callback
with a COMPLETED charge status proceeds to mark the order paid — an unverified callback is treated as
verification failure and never marks an order paid.

## The four Amazon Checkout v2 API calls

`Client/AmazonPayClient` is the interface with four methods; `Client/HttpAmazonPayClient` is the only
implementation, aliased onto it via `#[AsAlias(AmazonPayClient::class)]`:

1. `createCheckoutSession(array $payload): string` — returns the session ID
2. `readCheckoutSession(string $sessionId): array` — returns the full session object
3. `updateCheckoutSession(string $sessionId, array $payload): array` — PATCH; returns the updated session
4. `completeCheckoutSession(string $sessionId, string $chargePermissionId): array` — POST; returns the
   charge and its status

Every non-2xx response, and every transport-level failure from `ApiHttpClient`, is normalised into
`Exception/AmazonPayApiException` with a distinct exception code per call site, which
`AmazonPayPaymentMethod` catches and turns into `PaymentResult::failed()` so the order stays unpaid
instead of the checkout breaking.

## The API mock

The HTTP tests run against the repo's **shared WireMock mock**, not a client double — see
`Build/mocks/README.md`. The Amazon stubs live under
`Build/mocks/wiremock/mappings/payment/amazon/checkout-sessions/`, mirroring the URL paths. Behaviour is
**request-driven**, keyed on the session ID a test already controls:

- `create-session.json` — any create-session request succeeds, returning session ID `amazon_session_1`.
- `read-session.json` — reading session `amazon_session_1` returns the session object with buyer address
  and charge status.
- `update-session.json` — updating session `amazon_session_1` succeeds and returns the updated session.
- `complete-session.json` — completing session `amazon_session_1` returns a charge object with status
  COMPLETED.
- `complete-session-pending.json` — completing session `amazon_session_pending` returns a charge with
  status PENDING, exercising the "not yet completed" pending path.
- `complete-session-failed.json` — completing session `amazon_session_failed` returns a charge with
  status DECLINED, exercising the failure path.

`runTests.sh -s functional` starts one `wiremock/wiremock` container with those mappings mounted,
`waitFor`s port 8080, and passes `MOCK_BASE_URL` to the test container. The extension is pointed at it
purely through `apiBaseUrl` = `MOCK_BASE_URL/payment/amazon` — no client change is needed. To run it by
hand:

```shell
podman run --rm -p 8080:8080 -v "$PWD/Build/mocks/wiremock:/home/wiremock:ro" docker.io/wiremock/wiremock:3.10.0
```

## Testing

Every HTTP path is exercised against the real mock over HTTP — no client double.
`Tests/Functional/AbstractAmazonPayMockTestCase` (extending the shared `AbstractApiMockTestCase`) skips
when `MOCK_BASE_URL` is unset and assembles a `configuration()`/`client()` pointed at the mock.

- `Tests/Unit/Configuration/AmazonPayConfigurationFactoryTest` — the config layering (extension defaults,
  site overrides, empty-inherits, `apiBaseUrl` override), using a PHPUnit mock of `ExtensionConfiguration`
  (**not** an anonymous subclass — it is `readonly` in TYPO3 v14, so subclassing it is a fatal there).
- `Tests/Functional/Client/HttpAmazonPayClientTest` — creating a session, reading a session, updating a
  session, completing a session (COMPLETED/PENDING/FAILED status paths), and failure paths
  (`AmazonPayApiException` with distinct codes per call site).
- `Tests/Functional/Payment/AmazonPayPaymentMethodTest` — the full `AmazonPayPaymentMethod` behaviour: initiate,
  first return with review/update, second return with complete, webhook handling (verified signature,
  invalid signature, missing session ID), and idempotency on replayed calls.

## Planned: refunds and cancellations (phase 2)

This release implements only `RedirectPaymentMethodInterface` (create session, redirect, review and
complete, webhook confirm). The core `RefundablePaymentMethodInterface` (`cancel()`, `refund()`) is not
implemented, so the backend order module offers no refund/cancel action for an Amazon-paid order yet.
That is planned as a later phase, calling Amazon's refund API the same way `HttpAmazonPayClient` already
calls session-create/read/update/complete — reusing the same request-signing/HTTP/config-resolution
layers, not a parallel client.
