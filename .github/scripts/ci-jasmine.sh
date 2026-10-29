#!/bin/sh
set -eu

usage='usage: ci-jasmine.sh <magento-dir> [theme]...'
magento=${1:?$usage}
shift
[ "$#" -gt 0 ] || set -- luma
jasmine_core=${JASMINE_CORE_VERSION:-5.13.0}
source=$(cd "$(dirname "$0")/../.." && pwd)
specs=dev/tests/js/jasmine/tests/app/code

area_of() {
    case "$1" in
        luma|blank) echo frontend ;;
        backend) echo adminhtml ;;
        *) echo "unknown theme: $1" >&2; return 1 ;;
    esac
}

file_option() {
    area=$1
    modules=$(cd "$source/$specs" && find . -path "./Magento/*/$area/*" -name '*.js' ! -name '*.conf.js' | cut -d/ -f3 | sort -u | paste -sd, -)
    if [ -z "$modules" ]; then
        echo "no $area spec under $specs" >&2
        return 1
    fi
    case "$modules" in
        *,*) modules="{$modules}" ;;
    esac
    echo "--file=$specs/Magento/$modules/$area/**/!(*.conf).js"
}

for theme in "$@"; do
    area_of "$theme" > /dev/null
    file_option "$(area_of "$theme")" > /dev/null
done

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

status=0
for theme in "$@"; do
    echo "::group::spec:$theme"
    npx grunt "spec:$theme" "$(file_option "$(area_of "$theme")")" || status=$?
    echo "::endgroup::"
done
exit "$status"
