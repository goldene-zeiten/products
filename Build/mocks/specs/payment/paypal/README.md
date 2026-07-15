# PayPal API — reference specs

PayPal publishes the official OpenAPI specifications for the REST APIs the
`products_payment_paypal` package talks to. Rather than vendoring the multi-megabyte YAMLs, they are
pinned here by reference — they are the source the WireMock mappings under
`Build/mocks/wiremock/mappings/payment/paypal/` are derived from.

- **Orders v2** (create order, capture) —
  <https://github.com/paypal/paypal-rest-api-specifications/blob/main/openapi/checkout_orders_v2.json>
- **OAuth 2.0 token** (client-credentials grant) —
  <https://developer.paypal.com/api/rest/authentication/>
- **Webhooks — verify signature** (`/v1/notifications/verify-webhook-signature`) —
  <https://github.com/paypal/paypal-rest-api-specifications/blob/main/openapi/notifications_v1.json>

The mappings are the mock's definition; these specs are the contract they are checked against by hand.
Re-pin them when the package targets a newer API version.
