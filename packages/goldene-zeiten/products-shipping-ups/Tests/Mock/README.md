# Local UPS mock

A dockerized [Stoplight Prism](https://github.com/stoplightio/prism) mock so the extension's HTTP path
can be exercised without UPS credentials or hitting real UPS infrastructure.

The image bakes in a combined OpenAPI spec covering both UPS endpoints this extension calls (OAuth token
+ Rating "Shop") so a single container serves both under one base URL. Its **source lives in the
monorepo** at `Build/mocks/ups-rating/` (Dockerfile + `spec/ups-mock.yaml`, plus UPS's own specs pinned as
the reference it is derived from) and is published to GHCR by the `build-mock-images` workflow.

## Running

This repository drives containers with `podman`/`docker` directly (no compose):

```shell
Tests/Mock/start-mock.sh
# which is just:  podman run --rm -p 4010:4010 ghcr.io/goldene-zeiten/products-ups-rating-mock:latest
```

The image is published to GHCR on the first push to `main`. Before then — or to run an uncommitted change
to the spec — build it from the monorepo first:

```shell
podman build -t ghcr.io/goldene-zeiten/products-ups-rating-mock:latest Build/mocks/ups-rating
```

Prism then serves the mock on <http://localhost:4010> — the OAuth endpoint at
`/security/v1/oauth/token` and rating at `/api/rating/v2409/Shop`.

## Pointing the extension at it

Set the API base URL override to `http://localhost:4010` — either the Extension Configuration
`apiBaseUrl`, or the site setting `products.shipping.ups.apiBaseUrl`. Any non-empty client id / secret
works; the mock does not check them. The checkout then shows the mock's UPS Standard / UPS Saver rates.

## Automated integration test

`Tests/Functional/Integration/UpsMockIntegrationTest.php` drives the real Guzzle client against the mock.
It **runs automatically** as part of `Build/Scripts/runTests.sh -s functional`: the runner builds this
mock, starts it, `waitFor`s port 4010 (so the test never races the container), and hands the test its URL.
Run plain phpunit instead and it **skips** unless `UPS_MOCK_BASE_URL` is set — so it never fails just for
lack of a running mock.
