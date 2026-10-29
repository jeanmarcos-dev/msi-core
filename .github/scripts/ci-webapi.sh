#!/bin/sh
set -eu

usage='usage: ci-webapi.sh <magento-dir> rest|soap|graphql [phpunit arguments...]'
magento=${1:?$usage}
adapter=${2:?$usage}
shift 2
source=$(cd "$(dirname "$0")/../.." && pwd)

cd "$magento/dev/tests/api-functional"
php "$source/.github/scripts/ci-webapi-config.php" "$PWD" "$magento/vendor/jeanmarcos/msi-core" "$adapter"
exec php -d memory_limit=-1 ../../../vendor/bin/phpunit --configuration "phpunit_$adapter.xml" --colors=never --fail-on-empty-test-suite "$@"
