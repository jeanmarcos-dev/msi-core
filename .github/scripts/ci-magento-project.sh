#!/bin/sh
set -eu

platform=${1:?usage: ci-magento-project.sh magento|mage-os <target-dir>}
target=${2:?usage: ci-magento-project.sh magento|mage-os <target-dir>}
source=$(cd "$(dirname "$0")/../.." && pwd)

case "$platform" in
    magento)
        vendor=magento
        repository=https://mirror.mage-os.org/
        ;;
    mage-os)
        vendor=mage-os
        repository=https://repo.mage-os.org/
        ;;
    *)
        echo "unknown platform: $platform" >&2
        exit 2
        ;;
esac

probe=$(mktemp -d)
trap 'rm -rf "$probe"' EXIT
version=$(php "$source/.github/scripts/ci-newest-release.php" "$probe" "$vendor/product-community-edition" "$repository" "$source")

composer create-project --no-interaction --no-install --repository-url="$repository" "$vendor/project-community-edition:$version" "$target"
cd "$target"
mkdir -p app/etc
composer config --no-interaction repositories.0 composer "$repository"
composer config --no-interaction repositories.msi-core "{\"type\":\"path\",\"url\":\"$source\",\"options\":{\"symlink\":false}}"
composer config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config --no-interaction allow-plugins.laminas/laminas-dependency-plugin true
composer config --no-interaction 'allow-plugins.magento/*' true
composer require --no-interaction --no-update 'jeanmarcos/msi-core:*@dev'
composer install --no-interaction --no-progress
