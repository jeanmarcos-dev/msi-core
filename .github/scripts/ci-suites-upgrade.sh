#!/bin/sh
set -eu

usage='usage: ci-suites-upgrade.sh <magento-dir> <previous-source> <current-source> <work-dir>'
magento=${1:?$usage}
previous=${2:?$usage}
current=${3:?$usage}
work=${4:?$usage}
magento=$(cd "$magento" && pwd)
mkdir -p "$work"
work=$(cd "$work" && pwd)
here=$(cd "$(dirname "$0")" && pwd)
suites="$here/suites"
allowlist=${ALLOWLIST:-$here/../suites/schema-allowlist.txt}
upgraded=${DB_NAME:-magento_suites_upgrade}
fresh=${FRESH_DB_NAME:-magento_suites_fresh}
baseurl=${WEBAPI_BASE_URL:-http://127.0.0.1:8080}
export MAGENTO_ROOT="$magento"

run() {
    php -d memory_limit=-1 "$@"
}

install_into() {
    mysql --host="${DB_HOST:-127.0.0.1}" --user="${DB_USER:-root}" --password="${DB_PASSWORD:-root}" \
        --execute="CREATE DATABASE IF NOT EXISTS \`$1\`"
    rm -f "$magento/app/etc/env.php"
    DB_NAME="$1" sh "$here/ci-webapi-install.sh" "$magento" "$baseurl/"
}

compare() {
    if ! diff -u "$1" "$2"; then
        echo "$3" >&2
        exit 1
    fi
}

echo "::group::install the previous release and populate it"
sh "$here/ci-suites-swap.sh" "$magento" "$previous"
install_into "$upgraded"
cd "$magento"
run "$suites/seed-catalog.php" 60
run "$suites/topology.php" 60 single
php bin/magento config:set cataloginventory/source_reservations/enabled 1
php bin/magento cache:flush
run "$suites/upgrade-populate.php"
worker=1
pids=
while [ "$worker" -le 3 ]; do
    run "$suites/checkout-worker.php" "$worker" 5 &
    pids="$pids $!"
    worker=$((worker + 1))
done
for pid in $pids; do
    wait "$pid"
done
php bin/magento indexer:reindex
run "$suites/upgrade-snapshot.php" "$work/before.json"
echo "::endgroup::"

echo "::group::upgrade to the current release"
sh "$here/ci-suites-swap.sh" "$magento" "$current"
php -d memory_limit=-1 bin/magento setup:upgrade --keep-generated
php bin/magento cache:flush
run "$suites/upgrade-snapshot.php" "$work/after-upgrade.json"
compare "$work/before.json" "$work/after-upgrade.json" "the upgrade changed stock, reservations or salable quantities"
php bin/magento indexer:reindex
run "$suites/upgrade-snapshot.php" "$work/after-reindex.json"
compare "$work/before.json" "$work/after-reindex.json" "the reindex after the upgrade changed stock, reservations or salable quantities"
echo "::endgroup::"

echo "::group::release the orders placed before the upgrade"
run "$suites/cancel-orders.php"
run "$suites/ledger.php"
echo "::endgroup::"

echo "::group::compare the upgraded schema with a fresh install"
install_into "$fresh"
sh "$here/ci-suites-schema.sh" "$upgraded" "$fresh" "$allowlist"
echo "::endgroup::"
