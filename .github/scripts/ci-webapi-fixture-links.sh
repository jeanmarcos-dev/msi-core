#!/bin/sh
set -eu

magento=${1:?usage: ci-webapi-fixture-links.sh <magento-dir>}
source=$magento/vendor/jeanmarcos/msi-core

for tests in "$source"/*/Test; do
    [ -d "$tests" ] || continue
    module=$(basename "$(dirname "$tests")")
    target=$magento/app/code/Magento/$module
    mkdir -p "$target"
    ln -sfn "$tests" "$target/Test"
done
