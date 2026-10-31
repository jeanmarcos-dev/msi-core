#!/bin/sh
set -eu

usage='usage: ci-suites-swap.sh <magento-dir> <msi-core-source>'
magento=${1:?$usage}
source=${2:?$usage}

installed="$magento/vendor/jeanmarcos/msi-core"
rm -rf "$installed"
mkdir -p "$installed"
tar -C "$source" --exclude=.git -cf - . | tar -C "$installed" -xf -

cd "$magento"
composer dump-autoload --no-interaction
rm -rf generated/code generated/metadata var/cache var/page_cache var/view_preprocessed
