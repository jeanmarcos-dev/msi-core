#!/bin/sh
set -eu

usage='usage: ci-mftf-warmup.sh <magento-dir>'
magento=${1:?$usage}

cd "$magento"
php -d memory_limit=-1 bin/magento setup:static-content:deploy -f en_US \
    --area frontend --area adminhtml \
    --theme Magento/luma --theme Magento/backend
