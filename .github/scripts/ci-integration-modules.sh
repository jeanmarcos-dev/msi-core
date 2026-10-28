#!/bin/sh
set -eu

usage='usage: ci-integration-modules.sh <magento-dir> <report-dir> <module>...'
magento=${1:?$usage}
reports=${2:?$usage}
if [ "$#" -lt 3 ]; then
    echo "$usage" >&2
    exit 2
fi
shift 2
here=$(cd "$(dirname "$0")" && pwd)

mkdir -p "$reports"
reports=$(cd "$reports" && pwd)

failed=
for module in "$@"; do
    echo "::group::$module"
    if sh "$here/ci-integration.sh" "$magento" \
        --fail-on-empty-test-suite \
        --filter "Magento\\\\${module}\\\\Test\\\\" \
        --log-junit "$reports/$module.xml"; then
        echo "passed: $module"
    else
        echo "failed: $module"
        failed="$failed $module"
    fi
    echo "::endgroup::"
done

if [ -n "$failed" ]; then
    echo "failed modules:$failed" >&2
    exit 1
fi
