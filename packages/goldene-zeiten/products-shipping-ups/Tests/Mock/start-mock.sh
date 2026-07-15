#!/bin/sh

#
# Runs the local UPS mock (a Stoplight Prism instance serving the combined OAuth + Rating spec) on
# port 4010, using podman or docker directly - this repository drives containers with the plain runner,
# not compose, matching Build/Scripts/runTests.sh.
#
# Point the extension at it with the API base URL override http://localhost:4010 (Extension Configuration
# apiBaseUrl, or site setting products.shipping.ups.apiBaseUrl). Any non-empty client id / secret works.
#
# The image is published to GHCR on the first push to main. Before then (or to run an uncommitted change),
# build it from the monorepo first:
#
#   podman build -t ghcr.io/goldene-zeiten/products-ups-rating-mock:latest Build/mocks/ups-rating
#
set -e

IMAGE="ghcr.io/goldene-zeiten/products-ups-rating-mock:latest"
BIN="$(command -v podman || command -v docker || true)"

if [ -z "${BIN}" ]; then
    echo "Neither podman nor docker was found on PATH." >&2
    exit 1
fi

exec "${BIN}" run --rm -p 4010:4010 "${IMAGE}"
