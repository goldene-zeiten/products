#!/bin/sh

#
# Builds the disposable TYPO3 instance used for the Playwright acceptance suite. Run from
# INSIDE a container (see Build/Scripts/runTests.sh's "acceptance" suite) - never on the host,
# same rule as every other composer/php invocation in this repository.
#
# The instance is built per COMBINATION (env PRODUCTS_COMBO): only that combination's
# payment/shipping add-ons are installed and configured (against the WireMock mock at
# env MOCK_BASE_URL), so each run is a real, isolated install - the truest way to catch a
# split/add-on breaking the shop. The default "baseline" installs no payment/shipping add-on,
# which is the inert state the pre-existing specs assert.
#
# The instance's vendor/goldene-zeiten/* packages are symlinks back to this monorepo's packages/
# (composer path-repo). The instance lives beside the functional tests' own throwaway instances,
# under .Build/Web/typo3temp/var/tests/: nothing analyses that directory - cgl and phpstan look at
# packages/ and packages-dev/ - so a leftover instance cannot poison another suite, and one clean
# rule already removes them all.
#
# Usage: setupInstance.sh <core-version:13|14> <db-driver:sqlite|mysqli> [db-host] [db-name] [db-user] [db-password]
#        env PRODUCTS_COMBO=<baseline|paypal|stripe|klarna|ups|dhl|dhl-stripe|ups-paypal|all>
#        env MOCK_BASE_URL=http://mock-<suffix>:8080
#
set -e

CORE_VERSION="${1:-13}"
DB_DRIVER="${2:-sqlite}"
DB_HOST="${3:-}"
DB_NAME="${4:-}"
DB_USER="${5:-}"
DB_PASSWORD="${6:-}"
COMBO="${PRODUCTS_COMBO:-baseline}"
MOCK="${MOCK_BASE_URL:-}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
INSTANCE_PATH="${ROOT_DIR}/.Build/Web/typo3temp/var/tests/acceptance"

echo "Building acceptance instance for combination '${COMBO}' (mock: ${MOCK:-none})."

# Which payment/shipping add-on packages this combination installs. Everything else is base.
COMBO_PACKAGES=""
case "${COMBO}" in
    baseline) ;;
    paypal) COMBO_PACKAGES="goldene-zeiten/products-payment-paypal" ;;
    stripe) COMBO_PACKAGES="goldene-zeiten/products-payment-stripe" ;;
    klarna) COMBO_PACKAGES="goldene-zeiten/products-payment-klarna" ;;
    amazon) COMBO_PACKAGES="goldene-zeiten/products-payment-amazon" ;;
    ups) COMBO_PACKAGES="goldene-zeiten/products-shipping-ups" ;;
    dhl) COMBO_PACKAGES="goldene-zeiten/products-shipping-dhl-express" ;;
    dhl-stripe) COMBO_PACKAGES="goldene-zeiten/products-shipping-dhl-express goldene-zeiten/products-payment-stripe" ;;
    ups-paypal) COMBO_PACKAGES="goldene-zeiten/products-shipping-ups goldene-zeiten/products-payment-paypal" ;;
    all) COMBO_PACKAGES="goldene-zeiten/products-shipping-ups goldene-zeiten/products-shipping-dhl-express goldene-zeiten/products-payment-paypal goldene-zeiten/products-payment-stripe goldene-zeiten/products-payment-klarna goldene-zeiten/products-payment-amazon" ;;
    *) echo "Unknown PRODUCTS_COMBO '${COMBO}'." >&2; exit 1 ;;
esac

BASE_PACKAGES="goldene-zeiten/products-core goldene-zeiten/products-search goldene-zeiten/products-recently-viewed goldene-zeiten/products-wishlist goldene-zeiten/products-credit-points goldene-zeiten/products-voucher goldene-zeiten/products-watermark goldene-zeiten/products-dataset-import"

rm -rf "${INSTANCE_PATH}"
mkdir -p "${INSTANCE_PATH}"

# Build the "require" entries: base distribution + base add-ons + this combination's add-ons.
REQUIRE="        \"typo3/cms-base-distribution\": \"^${CORE_VERSION}\","
for PKG in ${BASE_PACKAGES} ${COMBO_PACKAGES}; do
    REQUIRE="${REQUIRE}
        \"${PKG}\": \"*\","
done
REQUIRE="${REQUIRE%,}"

cat > "${INSTANCE_PATH}/composer.json" <<EOF
{
    "name": "goldene-zeiten/products-acceptance-instance",
    "type": "project",
    "description": "Disposable TYPO3 instance for EXT:products Playwright acceptance tests. Rebuilt by Tests/Acceptance/setupInstance.sh on every run - never committed.",
    "license": "GPL-2.0-or-later",
    "repositories": [
        {"type": "path", "url": "${ROOT_DIR}/packages/*/*", "options": {"symlink": true, "versions": {"goldene-zeiten/products-api-client": "1.0.0", "goldene-zeiten/products-core": "1.0.0", "goldene-zeiten/products-search": "1.0.0", "goldene-zeiten/products-recently-viewed": "1.0.0", "goldene-zeiten/products-wishlist": "1.0.0", "goldene-zeiten/products-credit-points": "1.0.0", "goldene-zeiten/products-voucher": "1.0.0", "goldene-zeiten/products-watermark": "1.0.0", "goldene-zeiten/products-shipping-ups": "1.0.0", "goldene-zeiten/products-shipping-dhl-express": "1.0.0", "goldene-zeiten/products-payment-paypal": "1.0.0", "goldene-zeiten/products-payment-stripe": "1.0.0", "goldene-zeiten/products-payment-klarna": "1.0.0", "goldene-zeiten/products-payment-amazon": "1.0.0"}}},
        {"type": "path", "url": "${ROOT_DIR}/Tests/Acceptance/Packages/dataset_import", "options": {"symlink": true}}
    ],
    "require": {
${REQUIRE}
    },
    "config": {
        "allow-plugins": true,
        "vendor-dir": "vendor",
        "sort-packages": true
    },
    "extra": {
        "typo3/cms": {
            "web-dir": "public"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
EOF

cd "${INSTANCE_PATH}"
composer install --no-progress --no-interaction

mkdir -p config/system
cat > config/system/additional.php <<'EOF'
<?php
// Acceptance instance only: containers reach this by a name that changes per run, and CI needs
// to see *why* a spec failed rather than a blank production error page.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = 1;
// This instance is deliberately served over plain HTTP (no TLS cert to manage for a disposable
// per-run container) - TYPO3's default cookieSecure=1 silently refuses to ever send the FE
// session cookie over a non-HTTPS connection, which breaks basket/login state entirely.
$GLOBALS['TYPO3_CONF_VARS']['FE']['cookieSecure'] = 0;
// checkFeUserPid defaults to true, restricting FE authentication to fe_users rows living under a
// configured storage pid this disposable instance's site config never sets up - without this,
// the demo shopper's login is silently rejected regardless of a correct password.
$GLOBALS['TYPO3_CONF_VARS']['FE']['checkFeUserPid'] = false;
EOF

# Configure only this combination's methods, pointed at the WireMock mock. The methods resolve their
# credentials through ExtensionConfiguration (ApiSettingsResolver), so this is all they need to become
# available at checkout. Left unconfigured, a method stays inert - which is how the disabled state is
# tested.
append_config() {
    cat >> config/system/additional.php
}
case " ${COMBO_PACKAGES} " in
    *" goldene-zeiten/products-payment-paypal "*) append_config <<EOF
\$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_payment_paypal'] = [
    'environment' => 'sandbox', 'clientId' => 'mock-client', 'clientSecret' => 'secret',
    'webhookId' => 'WEBHOOK-OK', 'brandName' => 'Acceptance Shop', 'apiBaseUrl' => '${MOCK}/payment/paypal',
];
EOF
    ;;
esac
case " ${COMBO_PACKAGES} " in
    *" goldene-zeiten/products-payment-stripe "*) append_config <<EOF
\$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_payment_stripe'] = [
    'secretKey' => 'sk_test_mock', 'webhookSecret' => 'whsec_test', 'apiBaseUrl' => '${MOCK}/payment/stripe',
];
EOF
    ;;
esac
case " ${COMBO_PACKAGES} " in
    *" goldene-zeiten/products-payment-klarna "*) append_config <<EOF
\$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_payment_klarna'] = [
    'environment' => 'playground', 'username' => 'mock-user', 'password' => 'mock-pass', 'apiBaseUrl' => '${MOCK}/payment/klarna',
];
EOF
    ;;
esac
case " ${COMBO_PACKAGES} " in
    *" goldene-zeiten/products-payment-amazon "*) append_config <<EOF
\$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_payment_amazon'] = [
    'region' => 'eu', 'environment' => 'sandbox', 'publicKeyId' => 'SANDBOX-AMZN-TEST-KEY',
    'privateKey' => '${ROOT_DIR}/packages/goldene-zeiten/products-payment-amazon/Tests/Functional/Fixtures/test_private_key.pem',
    'storeId' => 'amzn1.application-oa2-client.test', 'merchantStoreName' => 'Acceptance Shop',
    'apiBaseUrl' => '${MOCK}/payment/amazon',
];
EOF
    ;;
esac
case " ${COMBO_PACKAGES} " in
    *" goldene-zeiten/products-shipping-ups "*) append_config <<EOF
\$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_shipping_ups'] = [
    'environment' => 'sandbox', 'clientId' => 'mock-client', 'clientSecret' => 'secret', 'accountNumber' => 'ACC123',
    'originPostCode' => '80331', 'originCountryCode' => 'DE', 'originCity' => 'Munich', 'usedServices' => '',
    'weightUnit' => 'KGS', 'apiBaseUrl' => '${MOCK}/shipping/ups',
];
EOF
    ;;
esac
case " ${COMBO_PACKAGES} " in
    *" goldene-zeiten/products-shipping-dhl-express "*) append_config <<EOF
\$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_shipping_dhl_express'] = [
    'environment' => 'sandbox', 'accountNumber' => 'ACC123', 'username' => 'mock-user', 'password' => 'mock-pass',
    'originCountryCode' => 'DE', 'originPostCode' => '53113', 'originCityName' => 'Bonn', 'weightUnit' => 'metric',
    'usedProducts' => '', 'apiBaseUrl' => '${MOCK}/shipping/dhl-express',
];
EOF
    ;;
esac

if [ "${DB_DRIVER}" = "sqlite" ]; then
    vendor/bin/typo3 setup --force --no-interaction \
        --driver=sqlite \
        --admin-username=admin \
        --admin-user-password='AcceptanceTest123!' \
        --admin-email=admin@example.com \
        --project-name="Products Acceptance" \
        --server-type=apache
else
    vendor/bin/typo3 setup --force --no-interaction \
        --driver="${DB_DRIVER}" \
        --host="${DB_HOST}" \
        --dbname="${DB_NAME}" \
        --username="${DB_USER}" \
        --password="${DB_PASSWORD}" \
        --admin-username=admin \
        --admin-user-password='AcceptanceTest123!' \
        --admin-email=admin@example.com \
        --project-name="Products Acceptance" \
        --server-type=apache
fi

mkdir -p config/sites/products-acceptance
cp "${ROOT_DIR}/Tests/Acceptance/Fixtures/site-config.yaml" config/sites/products-acceptance/config.yaml

vendor/bin/typo3 dataset:import "${ROOT_DIR}/Tests/Acceptance/Fixtures/shop-demo.csv"

# Suppress a favicon 404 in the browser console, same reasoning as TYPO3 core's own acceptance
# instance setup (Build/Scripts/setupAcceptanceComposer.sh).
ln -snf vendor/typo3/cms-backend/Resources/Public/Icons/favicon.ico public/favicon.ico
