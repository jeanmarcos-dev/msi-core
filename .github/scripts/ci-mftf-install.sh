#!/bin/sh
set -eu

usage='usage: ci-mftf-install.sh <magento-dir> <base-url>'
magento=${1:?$usage}
baseurl=${2:?$usage}

cd "$magento"
php -d memory_limit=-1 bin/magento setup:install \
    --base-url="$baseurl" \
    --db-host="${DB_HOST:-127.0.0.1}" \
    --db-name="${DB_NAME:-magento_mftf}" \
    --db-user="${DB_USER:-root}" \
    --db-password="${DB_PASSWORD:-root}" \
    --search-engine=opensearch \
    --opensearch-host="${OPENSEARCH_HOST:-127.0.0.1}" \
    --opensearch-port="${OPENSEARCH_PORT:-9200}" \
    --admin-user=admin \
    --admin-password="${MFTF_ADMIN_PASSWORD:-123123q}" \
    --admin-email=admin@example.com \
    --admin-firstname=Admin \
    --admin-lastname=Admin \
    --backend-frontname=backend \
    --use-rewrites=1 \
    --session-save=db \
    --cleanup-database
php bin/magento config:set twofactorauth/general/force_providers google
php bin/magento security:tfa:google:set-secret admin "${MFTF_OTP_SECRET:-GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ}"
php bin/magento config:set cms/wysiwyg/enabled disabled
php bin/magento config:set admin/security/admin_account_sharing 1
php bin/magento config:set admin/security/use_form_key 0
php bin/magento config:set admin/captcha/enable 0
php bin/magento config:set customer/captcha/enable 0
php bin/magento config:set oauth/consumer/enable_integration_as_bearer 1
php bin/magento config:set webapi/jwtauth/admin_expiration 2880
php bin/magento indexer:set-mode realtime
php bin/magento deploy:mode:set developer --skip-compilation
