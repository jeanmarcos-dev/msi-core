#!/bin/sh
set -eu

magento=${1:?usage: ci-di-compile.sh <magento-dir>}

cd "$magento"
php bin/magento module:enable --all
php -d memory_limit=-1 bin/magento setup:di:compile
