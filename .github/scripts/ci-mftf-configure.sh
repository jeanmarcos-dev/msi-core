#!/bin/sh
set -eu

usage='usage: ci-mftf-configure.sh <magento-dir> <base-url>'
magento=${1:?$usage}
baseurl=${2:?$usage}

cd "$magento"
mkdir -p dev/tests/acceptance/utils
php -d memory_limit=-1 vendor/bin/mftf build:project \
    --MAGENTO_BASE_URL="$baseurl" \
    --MAGENTO_BACKEND_NAME=backend \
    --MAGENTO_ADMIN_USERNAME=admin \
    --SELENIUM_CLOSE_ALL_SESSIONS=false \
    --MAGENTO_CLI_WAIT_TIMEOUT=120
printf 'magento/MAGENTO_ADMIN_PASSWORD=%s\nmagento/tfa/OTP_SHARED_SECRET=%s\n' \
    "${MFTF_ADMIN_PASSWORD:-123123q}" \
    "${MFTF_OTP_SECRET:-GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ}" > dev/tests/acceptance/.credentials
