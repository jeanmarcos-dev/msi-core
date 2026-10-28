#!/bin/sh
set -eu

usage='usage: ci-jasmine.sh <magento-dir> [theme]'
magento=${1:?$usage}
theme=${2:-luma}
jasmine_core=${JASMINE_CORE_VERSION:-5.13.0}
source=$(cd "$(dirname "$0")/../.." && pwd)
specs=dev/tests/js/jasmine/tests/app/code

modules=$(cd "$source/$specs" && find . -name '*.test.js' | cut -d/ -f3 | sort -u | paste -sd, -)
if [ -z "$modules" ]; then
    echo "no *.test.js under $specs" >&2
    exit 1
fi
case "$modules" in
    *,*) modules="{$modules}" ;;
esac

mkdir -p "$magento/$specs"
cp -R "$source/$specs/." "$magento/$specs/"

cd "$magento"
cp Gruntfile.js.sample Gruntfile.js
cp package.json.sample package.json
npm install --no-audit --no-fund

runner=node_modules/grunt-contrib-jasmine/.jasmine/latest
pack=$(mktemp -d)
trap 'rm -rf "$pack"' EXIT
mkdir -p "$runner"
(cd "$pack" && npm pack "jasmine-core@$jasmine_core" --silent)
tar -xzf "$pack"/*.tgz -C "$runner" --strip-components=1

npx grunt "spec:$theme" "--file=$specs/Magento/$modules/**/*.test.js"
