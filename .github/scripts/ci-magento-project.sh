#!/bin/sh
set -eu

platform=${1:?usage: ci-magento-project.sh magento|mage-os <target-dir>}
target=${2:?usage: ci-magento-project.sh magento|mage-os <target-dir>}
source=$(cd "$(dirname "$0")/../.." && pwd)

case "$platform" in
    magento)
        project='magento/project-community-edition:>=2.4.9 <2.4.10'
        repository=https://mirror.mage-os.org/
        ;;
    mage-os)
        project='mage-os/project-community-edition:>=3.0 <4.0'
        repository=https://repo.mage-os.org/
        ;;
    *)
        echo "unknown platform: $platform" >&2
        exit 2
        ;;
esac

composer create-project --no-interaction --no-install --repository-url="$repository" "$project" "$target"
cd "$target"
mkdir -p app/etc
composer config --no-interaction repositories.0 composer "$repository"
composer config --no-interaction repositories.msi-core path "$source"
composer config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config --no-interaction allow-plugins.laminas/laminas-dependency-plugin true
composer config --no-interaction 'allow-plugins.magento/*' true
composer require --no-interaction --no-update 'jeanmarcos/msi-core:*@dev'
composer install --no-interaction --no-progress
