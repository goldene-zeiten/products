# A self-contained UPS rating mock: Stoplight Prism serving the combined OAuth + Rating spec baked in, so
# it runs with a single `docker run` and no volume mounts. Published to GHCR by
# .github/workflows/build-mock-images.yml and pulled by the extension's Tests/Mock/docker-compose.yml.
FROM stoplight/prism:4

COPY spec/ups-mock.yaml /spec/ups-mock.yaml

EXPOSE 4010

CMD ["mock", "-h", "0.0.0.0", "/spec/ups-mock.yaml"]
