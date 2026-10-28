#!/bin/sh
set -eu

magento=${1:?usage: ci-webapi-install.sh <magento-dir> <base-url>}
baseurl=${2:?usage: ci-webapi-install.sh <magento-dir> <base-url>}
source=$(cd "$(dirname "$0")/../.." && pwd)

cd "$magento"
php -d memory_limit=-1 bin/magento setup:install \
    --base-url="$baseurl" \
    --db-host="${DB_HOST:-127.0.0.1}" \
    --db-name="${DB_NAME:-magento_webapi_tests}" \
    --db-user="${DB_USER:-root}" \
    --db-password="${DB_PASSWORD:-root}" \
    --search-engine=opensearch \
    --opensearch-host="${OPENSEARCH_HOST:-127.0.0.1}" \
    --opensearch-port="${OPENSEARCH_PORT:-9200}" \
    --admin-user=admin \
    --admin-password=123123q \
    --admin-email=admin@example.com \
    --admin-firstname=Admin \
    --admin-lastname=Admin \
    --backend-frontname=backend \
    --use-rewrites=0 \
    --session-save=db \
    --cleanup-database \
    --disable-modules=Magento_TwoFactorAuth,Magento_AdminAdobeImsTwoFactorAuth
php bin/magento config:set --lock-env web/unsecure/base_url "$baseurl"
php bin/magento config:set --lock-env web/secure/base_url "$baseurl"
php bin/magento config:set --lock-env oauth/consumer/enable_integration_as_bearer 1
php bin/magento config:set --lock-env catalog/search/engine opensearch
php bin/magento config:set --lock-env catalog/search/opensearch_server_hostname "${OPENSEARCH_HOST:-127.0.0.1}"
php bin/magento config:set --lock-env catalog/search/opensearch_server_port "${OPENSEARCH_PORT:-9200}"
php bin/magento config:set --lock-env customer/account_share/scope 0
php bin/magento config:set --lock-env catalog/downloadable/disable_guest_checkout 0
php bin/magento config:set --lock-env catalog/downloadable/shareable 1
sh "$source/.github/scripts/ci-webapi-fixture-links.sh" "$magento"
