#!/bin/sh
set -eu

magento=${1:?usage: ci-integration.sh <magento-dir> [phpunit arguments...]}
shift
source=$(cd "$(dirname "$0")/../.." && pwd)

cd "$magento/dev/tests/integration"
php "$source/.github/scripts/ci-integration-config.php" "$PWD" "$magento/vendor/jeanmarcos/msi-core"
exec php -d memory_limit=-1 ../../../vendor/bin/phpunit --configuration phpunit.xml --colors=never "$@"
