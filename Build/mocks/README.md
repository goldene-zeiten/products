# API mocks

One shared [WireMock](https://wiremock.org) server mocks every third-party HTTP API the packages talk to,
so the functional tests exercise the real HTTP path (real client, real requests, real error handling)
without credentials or live infrastructure — and without a separate mock server per API.

`Build/Scripts/runTests.sh -s functional` starts one `wiremock/wiremock` container with `wiremock/`
mounted, waits for it, and passes its base URL to the test container as `MOCK_BASE_URL`.

## Layout

```
wiremock/
  mappings/<domain>/<vendor>/<endpoint>/*.json   # stub mappings, mirroring the extension namespace
  __files/                                        # (optional) large response bodies
specs/<domain>/<vendor>/*.yaml                    # the vendor's real OpenAPI specs, kept as the reference
```

The folder path mirrors the PHP namespace and the URL. For UPS
(`GoldeneZeiten\Products\Shipping\Ups`), the mappings live under `mappings/shipping/ups/` and match URLs
under `/shipping/ups/…`. So a package points its API base-URL override at `MOCK_BASE_URL/shipping/ups`,
collision-free from every other API.

## Adding an API

1. Add `mappings/<domain>/<vendor>/…` stub files matching `urlPath` `/<domain>/<vendor>/<real-api-path>`.
2. Pin the vendor's OpenAPI spec under `specs/<domain>/<vendor>/` as the reference the mappings are built
   from.
3. Give the extension an API-base-URL override setting (like `products.shipping.ups.apiBaseUrl`) and point
   it at `MOCK_BASE_URL/<domain>/<vendor>` in the tests.

Nothing in `runTests.sh` changes — the one WireMock server picks up the new mappings automatically.

## Scenarios (stateful behaviour)

Error and edge cases are driven by the request, so a test selects them purely through inputs it already
controls — no client changes, no special headers. For UPS the rating stubs key on the destination country:

| Destination country | Behaviour                                   |
|---------------------|---------------------------------------------|
| (any other)         | 200, two services                           |
| `XX`                | 400 "no rate" → provider yields to fallback |
| `YY`                | 500 server error                            |
| `ZZ`                | connection reset (transport fault)          |
| `SG`                | 200, single-shipment object (not a list)    |
| `RT`                | 401 then 200 (WireMock Scenario — retry)    |

and the OAuth stub returns 401 for the designated bad client id `authfail`. Stateful flows use WireMock
[Scenarios](https://wiremock.org/docs/stateful-behaviour/); reset them between tests with
`POST {MOCK_BASE_URL}/__admin/scenarios/reset`. The request journal (`GET {MOCK_BASE_URL}/__admin/requests`)
lets a test assert the exact outgoing request.

For PayPal (`mappings/payment/paypal/`, base URL `MOCK_BASE_URL/payment/paypal`) the order stubs key on
the PayPal order id in the capture URL:

| Capture order id       | Behaviour                                          |
|------------------------|----------------------------------------------------|
| `PAYPAL-ORDER-1`       | 201 `COMPLETED` (capture `CAPTURE-1`)              |
| `PAYPAL-ORDER-DECLINE` | 422 `INSTRUMENT_DECLINED`                          |
| `PAYPAL-ORDER-CAPTURED`| 422 `ORDER_ALREADY_CAPTURED` → treated as success |
| `PAYPAL-ORDER-RETRY`   | 401 then 201 (WireMock Scenario — token retry)    |

Create-order returns 422 when the amount is `0.00`; the webhook verify endpoint returns `SUCCESS` for the
designated webhook id `WEBHOOK-OK` and `FAILURE` otherwise. The generic OAuth flow of the shared
`products_api_client` package is covered by `mappings/api-client/oauth/` at `MOCK_BASE_URL/api-client`.
