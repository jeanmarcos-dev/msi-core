#!/bin/sh
set -eu

target=${1:?usage: ci-static-project.sh <target-dir>}
source=$(cd "$(dirname "$0")/../.." && pwd)
repository=https://mirror.mage-os.org/

probe=$(mktemp -d)
trap 'rm -rf "$probe"' EXIT
version=$(php "$source/.github/scripts/ci-newest-release.php" "$probe" magento/product-community-edition "$repository" "$source")

git clone --quiet --depth 1 --branch "$version" https://github.com/magento/magento2 "$target"
cd "$target"
composer config --no-interaction repositories.0 composer "$repository"
composer config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
composer config --no-interaction allow-plugins.laminas/laminas-dependency-plugin true
composer config --no-interaction 'allow-plugins.magento/*' true
composer update --lock --no-interaction
composer install --no-interaction --no-progress

for registration in "$source"/*/registration.php; do
    module=$(basename "$(dirname "$registration")")
    cp -R "$source/$module" "app/code/Magento/$module"
done

sed -i 's#<rule ref="\(.*Magento2Framework\)"/>#<rule ref="\1"><exclude name="Magento2Framework.Header.Copyright"/><exclude name="Magento2Framework.Header.CopyrightAnotherExtensionsFiles"/><exclude name="Magento2Framework.Header.CopyrightGraphQL"/></rule>#' dev/tests/static/framework/Magento/ruleset.xml
