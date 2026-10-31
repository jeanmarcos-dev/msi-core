#!/bin/sh
set -eu

usage='usage: ci-suites-stress.sh <magento-dir>'
magento=${1:?$usage}
magento=$(cd "$magento" && pwd)
suites=$(cd "$(dirname "$0")/suites" && pwd)
export MAGENTO_ROOT="$magento"

run() {
    php -d memory_limit=-1 "$@"
}

parallel() {
    script=$1
    workers=$2
    shift 2
    pids=
    worker=1
    while [ "$worker" -le "$workers" ]; do
        run "$suites/$script" "$worker" "$@" &
        pids="$pids $!"
        worker=$((worker + 1))
    done
    failed=0
    for pid in $pids; do
        wait "$pid" || failed=1
    done
    return "$failed"
}

cd "$magento"
echo "::group::topology"
run "$suites/topology.php" 300
php bin/magento config:set cataloginventory/source_reservations/enabled 1
php bin/magento cache:flush
php bin/magento indexer:reindex inventory
echo "::endgroup::"

echo "::group::e2e"
run "$suites/e2e.php"
echo "::endgroup::"

echo "::group::reservation path, 8 workers"
parallel stress-worker.php 8 25 SLR-2 3
echo "::endgroup::"

echo "::group::checkouts, 6 workers"
parallel checkout-worker.php 6 8
echo "::endgroup::"

echo "::group::cancel and ledger"
run "$suites/cancel-orders.php"
run "$suites/ledger.php"
echo "::endgroup::"
