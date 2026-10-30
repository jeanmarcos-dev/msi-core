#!/bin/sh
set -eu

usage='usage: ci-suites-conformance.sh <magento-dir> <expected-divergences-file>'
magento=${1:?$usage}
expected=${2:?$usage}
magento=$(cd "$magento" && pwd)
expected=$(cd "$(dirname "$expected")" && pwd)/$(basename "$expected")
suites=$(cd "$(dirname "$0")/suites" && pwd)
export MAGENTO_ROOT="$magento"

output=$(mktemp)
trap 'rm -f "$output" "$output.actual" "$output.expected"' EXIT

cd "$magento"
php -d memory_limit=-1 "$suites/conformance.php" > "$output" || true
cat "$output"

if ! grep -qE '^(CONFORMANT|DIVERGENT) conformance:' "$output"; then
    echo "the conformance suite did not reach its verdict" >&2
    exit 1
fi

grep -E '^  XX ' "$output" | sed -E 's/ +/ /g' | sort > "$output.actual" || true
sed -E 's/ +/ /g' "$expected" | sort > "$output.expected"

if ! diff -u "$output.expected" "$output.actual"; then
    echo "the divergences differ from $expected" >&2
    exit 1
fi
