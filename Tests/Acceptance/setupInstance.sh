#!/bin/sh

#
# Builds the disposable TYPO3 instance used for the Playwright acceptance suite. Run from
# INSIDE a container (see Build/Scripts/runTests.sh's "acceptance" suite) - never on the host,
# same rule as every other composer/php invocation in this repository.
#
# Usage: setupInstance.sh <core-version:13|14> [instance-path]
#
set -e

CORE_VERSION="${1:-13}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/../.." && pwd)"
INSTANCE_PATH="${2:-${ROOT_DIR}/Tests/Acceptance/Instance}"

rm -rf "${INSTANCE_PATH}"
mkdir -p "${INSTANCE_PATH}"

cat > "${INSTANCE_PATH}/composer.json" <<EOF
{
    "name": "goldene-zeiten/products-acceptance-instance",
    "type": "project",
    "description": "Disposable TYPO3 instance for EXT:products Playwright acceptance tests. Rebuilt by Tests/Acceptance/setupInstance.sh on every run - never committed.",
    "license": "GPL-2.0-or-later",
    "repositories": [
        {"type": "path", "url": "${ROOT_DIR}", "options": {"symlink": true}},
        {"type": "path", "url": "${ROOT_DIR}/Tests/Acceptance/Packages/dataset_import", "options": {"symlink": true}}
    ],
    "require": {
        "typo3/cms-base-distribution": "^${CORE_VERSION}",
        "goldene-zeiten/products": "*",
        "goldene-zeiten/products-dataset-import": "*"
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
EOF

vendor/bin/typo3 setup --force --no-interaction \
    --driver=sqlite \
    --admin-username=admin \
    --admin-user-password='AcceptanceTest123!' \
    --admin-email=admin@example.com \
    --project-name="Products Acceptance" \
    --server-type=apache

mkdir -p config/sites/products-acceptance
cp "${ROOT_DIR}/Tests/Acceptance/Fixtures/site-config.yaml" config/sites/products-acceptance/config.yaml

vendor/bin/typo3 dataset:import "${ROOT_DIR}/Tests/Acceptance/Fixtures/shop-demo.csv"

# Suppress a favicon 404 in the browser console, same reasoning as TYPO3 core's own acceptance
# instance setup (Build/Scripts/setupAcceptanceComposer.sh).
ln -snf vendor/typo3/cms-backend/Resources/Public/Icons/favicon.ico public/favicon.ico
