# DHL Express (MyDHL API) — reference spec

The `products_shipping_dhl_express` package rates against the **DHL Express MyDHL API** `Rate` capability — the
one DHL API with a public rate endpoint. (DHL Paket / Deutsche Post domestic has no public rating API.)
The WireMock mappings under `Build/mocks/wiremock/mappings/shipping/dhl-express/` are derived from it.

- **MyDHL API reference / OpenAPI download** —
  <https://developer.dhl.com/api-reference/dhl-express-mydhl-api>
- **Rate endpoint** — `GET /rates` (one-piece) — Basic auth, query parameters for origin/destination
  country + city + postcode, weight, dimensions, `plannedShippingDate`, `isCustomsDeclarable`,
  `unitOfMeasurement`. Response `products[]` with `totalPrice[]` (read the `currencyType: BILLC` variant)
  and `deliveryCapabilities.estimatedDeliveryDateAndTime`.
- A faithful OpenAPI-generated PHP mirror (spec v2.11.0) that the field names were pinned against:
  <https://github.com/Telrik/mydhl-api-php>

Re-pin from the developer portal (register + download the current OpenAPI file) before locking newer
field names into code.
