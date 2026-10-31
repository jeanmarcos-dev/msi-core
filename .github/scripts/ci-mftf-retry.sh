#!/bin/sh
set -eu

usage='usage: ci-mftf-retry.sh <magento-dir> <report-dir> <shard>'
magento=${1:?$usage}
report=${2:?$usage}
shard=${3:?$usage}
source=$(cd "$(dirname "$0")/../.." && pwd)
output=$magento/dev/tests/acceptance/tests/_output
results=$output/allure-results
generated=$magento/dev/tests/acceptance/tests/functional/Magento/_generated

cd "$magento"
failed=$(php "$source/.github/scripts/ci-mftf-retry.php" failed "$results")
if [ -z "$failed" ]; then
    echo "shard $shard failed without a failed test to retry"
    exit 1
fi

first=$report/first-run
mkdir -p "$first"
cp -R "$results/." "$first/"

rm -rf "$generated"
set --
while IFS= read -r name; do
    set -- "$@" "$name"
done <<EOF
$failed
EOF
echo "shard $shard retries: $*"
php -d memory_limit=-1 vendor/bin/mftf generate:tests --force "$@"

status=0
php -d memory_limit=-1 vendor/bin/mftf run:manifest "$generated/testManifest.txt" || status=$?

cp -Rn "$first/." "$results/"
if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
    php "$source/.github/scripts/ci-mftf-retry.php" flaky "$first" "$results" "$shard" >> "$GITHUB_STEP_SUMMARY"
fi
exit "$status"
