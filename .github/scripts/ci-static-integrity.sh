#!/bin/sh
set -eu

usage='usage: ci-static-integrity.sh <magento-dir> <baseline-file>'
magento=${1:?$usage}
baseline=${2:?$usage}
here=$(cd "$(dirname "$0")" && pwd)
junit=$magento/var/integrity-junit.xml

mkdir -p "$magento/var"
status=0
sh "$here/ci-static.sh" "$magento" integrity --log-junit "$junit" || status=$?
php "$here/ci-static-baseline.php" "$junit" "$baseline" "$magento" "$status"
