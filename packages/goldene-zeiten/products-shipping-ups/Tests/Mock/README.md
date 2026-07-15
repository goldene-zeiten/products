# Local UPS mock

A dockerized [Stoplight Prism](https://github.com/stoplightio/prism) mock so the extension's HTTP path
can be exercised without UPS credentials or hitting real UPS infrastructure.

The image bakes in a combined OpenAPI spec covering both UPS endpoints this extension calls (OAuth token
+ Rating "Shop") so a single container serves both under one base URL. Its **source lives in the
monorepo** at `Build/mocks/ups-rating/` (Dockerfile + `spec/ups-mock.yaml`, plus UPS's own specs pinned as
the reference it is derived from) and is published to GHCR by the `build-mock-images` workflow.

## Running

```shell
docker compose -f Tests/Mock/docker-compose.yml up      # or: podman compose ...
# or directly:
docker run --rm -p 4010:4010 ghcr.io/goldene-zeiten/products-ups-rating-mock:latest
```

Prism then serves the mock on <http://localhost:4010> — the OAuth endpoint at
`/security/v1/oauth/token` and rating at `/api/rating/v2409/Shop`.

## Pointing the extension at it

Set the API base URL override to `http://localhost:4010` — either the Extension Configuration
`apiBaseUrl`, or the site setting `products.shipping.ups.apiBaseUrl`. Any non-empty client id / secret
works; the mock does not check them. The checkout then shows the mock's UPS Standard / UPS Saver rates.

## Automated integration test

`Tests/Functional/Integration/UpsMockIntegrationTest.php` drives the real Guzzle client against the mock.
It is skipped unless `UPS_MOCK_BASE_URL` points at a reachable mock, so it never runs in the normal
hermetic suite (no container dependency, no startup race). With the mock reachable from the test runner,
export `UPS_MOCK_BASE_URL` and run the functional suite to execute it.
