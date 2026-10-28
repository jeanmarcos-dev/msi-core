#!/bin/sh
set -eu

magento=${1:?usage: ci-unit.sh <magento-dir>}
source=$(cd "$(dirname "$0")/../.." && pwd)

cd "$magento"
exec php vendor/bin/phpunit \
    --configuration "$source/.github/phpunit-unit.xml" \
    --bootstrap dev/tests/unit/framework/bootstrap.php \
    --cache-directory "$magento/var/phpunit-cache"
