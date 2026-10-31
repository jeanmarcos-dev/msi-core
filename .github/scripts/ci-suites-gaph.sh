#!/bin/sh
set -eu

usage='usage: ci-suites-gaph.sh <magento-dir> <rounds>'
magento=${1:?$usage}
rounds=${2:?$usage}
magento=$(cd "$magento" && pwd)
suites=$(cd "$(dirname "$0")/suites" && pwd)
export MAGENTO_ROOT="$magento"
warmup=${SUITES_GAPH_WARMUP:-3}
retries=${SUITES_GAPH_RETRIES:-3}
barrier="$magento/var/suites/gaph-go"

run() {
    php -d memory_limit=-1 "$@"
}

play() {
    run "$suites/gaph-setup.php" > /dev/null
    run "$suites/gaph-worker.php" 1 1 &
    first=$!
    run "$suites/gaph-worker.php" 2 2 &
    second=$!
    sleep "$warmup"
    mkdir -p "$(dirname "$barrier")"
    touch "$barrier"
    wait "$first" || true
    wait "$second" || true
    status=0
    run "$suites/gaph-check.php" 2 || status=$?
    return "$status"
}

cd "$magento"
oversold=0
undecided=0
round=1
while [ "$round" -le "$rounds" ]; do
    attempt=1
    while :; do
        status=0
        play || status=$?
        if [ "$status" -ne 3 ]; then
            break
        fi
        if [ "$attempt" -ge "$retries" ]; then
            undecided=$((undecided + 1))
            echo "round $round: no contention after $attempt attempts"
            break
        fi
        echo "round $round: no contention, playing it again"
        attempt=$((attempt + 1))
    done
    if [ "$status" -eq 1 ]; then
        oversold=$((oversold + 1))
        echo "round $round: OVERSOLD"
    elif [ "$status" -eq 0 ]; then
        echo "round $round: ok"
    fi
    round=$((round + 1))
done

run "$suites/gaph-teardown.php"
echo "gap-H oversold rounds: $oversold / $rounds, undecided: $undecided"
if [ "$oversold" -gt 0 ] || [ "$undecided" -gt 0 ]; then
    exit 1
fi
