#!/bin/sh
set -eu

magento=${1:?usage: ci-unit.sh <magento-dir>}

cd "$magento"
exec php vendor/bin/phpunit \
    --configuration "$magento/vendor/jeanmarcos/msi-core/.github/phpunit-unit.xml" \
    --bootstrap dev/tests/unit/framework/bootstrap.php \
    --cache-directory "$magento/var/phpunit-cache"
