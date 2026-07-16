# Acceptance tests (Playwright)

End-to-end browser tests driving the real shop. They run **per payment/shipping combination**: each
combination is a fresh, isolated TYPO3 instance that installs and configures only that combination's
add-ons (against a WireMock mock), so a split or add-on breaking the shop is caught for exactly the
combination it breaks in.

## Running

```shell
# the default "baseline" combination installs no payment/shipping add-on (the inert shop)
Build/Scripts/runTests.sh -s acceptance

# a specific combination
PRODUCTS_COMBO=stripe Build/Scripts/runTests.sh -s acceptance
PRODUCTS_COMBO=all    Build/Scripts/runTests.sh -s acceptance -d mariadb
```

CI runs the whole matrix (`.github/workflows/testcore13.yml` / `testcore14.yml`, the `acceptance` job).

## Combinations

Defined in `setupInstance.sh` (which packages get installed + configured) and mirrored in
`helper/combination.js` (which payment methods / carriers a spec should expect):

| `PRODUCTS_COMBO` | Installs / configures |
|------------------|-----------------------|
| `baseline`       | nothing (invoice + built-in table-rate only) — the disabled/inert state |
| `paypal` / `stripe` / `klarna` | that one payment method |
| `ups` / `dhl`    | that one live carrier |
| `dhl-stripe`, `ups-paypal` | a carrier + a payment method (pairwise) |
| `all`            | every payment method + both carriers (multi-active side effects) |

## How it fits together

- `Build/Scripts/runTests.sh` (`acceptance` case) starts a WireMock container on the run's network,
  builds the instance for `PRODUCTS_COMBO`, serves it, and runs Playwright with `PRODUCTS_COMBINATION`.
- `setupInstance.sh` installs base packages + the combination's add-ons, and writes their
  `ExtensionConfiguration` (credentials + `apiBaseUrl` -> the WireMock mock) into `config/system/additional.php`.
  Left unconfigured, a method stays inert — which is how the disabled state is tested.
- WireMock serves every gateway/carrier API (`Build/mocks/wiremock/mappings/...`); the same request-driven
  stubs the functional suite uses, incl. failure scenarios (declines, no-rate, 401 retry).
- Specs self-select via `helper/combination.js`: a payment spec skips unless its method is active; the
  table-rate delivery spec skips when a live carrier supersedes it; the multi-active spec runs only for `all`.

## Adding a combination

1. Add a `case` arm in `setupInstance.sh` listing the packages, plus an `append_config` block if a new
   method needs credentials.
2. Add the combination to `PAYMENTS` / `CARRIERS` in `helper/combination.js`.
3. Add a matrix entry to the `acceptance` job in `testcore13.yml` and `testcore14.yml`.

## Adding a new payment/shipping add-on

Because a new add-on installs cleanly into the baseline, add a combination for it (single + folded into
`all`) so its checkout behaviour and its coexistence with the others are both covered.
