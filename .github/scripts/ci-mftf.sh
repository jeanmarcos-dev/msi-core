#!/bin/sh
set -eu

usage='usage: ci-mftf.sh <magento-dir> <shard> <shards> <report-dir> [--tests a,b] [--group name]'
magento=${1:?$usage}
shard=${2:?$usage}
shards=${3:?$usage}
report=${4:?$usage}
shift 4
source=$(cd "$(dirname "$0")/../.." && pwd)
generated=$magento/dev/tests/acceptance/tests/functional/Magento/_generated

cd "$magento"
php -d memory_limit=-1 vendor/bin/mftf generate:tests --force
selection=$(php "$source/.github/scripts/ci-mftf-select.php" "$generated" "$magento/vendor/jeanmarcos/msi-core" "$shard" "$shards" "$@")

if [ -z "$selection" ]; then
    echo "shard $shard of $shards has no MFTF test to run"
    exit 0
fi

rm -rf "$generated"
set --
while IFS= read -r name; do
    set -- "$@" "$name"
done <<EOF
$selection
EOF
php -d memory_limit=-1 vendor/bin/mftf generate:tests --force "$@"

status=0
php -d memory_limit=-1 vendor/bin/mftf run:manifest "$generated/testManifest.txt" || status=$?

mkdir -p "$report"
cp -R dev/tests/acceptance/tests/_output "$report/_output"
exit "$status"
