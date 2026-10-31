#!/bin/sh
set -eu

usage='usage: ci-static.sh <magento-dir> strict-types|phpstan|xml|html|less|graphql|integrity|legacy [phpunit-option]...'
magento=${1:?$usage}
suite=${2:?$usage}
shift 2

configuration=phpunit.xml.dist
case "$suite" in
    strict-types) set -- --filter testStrictTypes "$@" ;;
    phpstan) set -- --filter testPhpStan "$@" ;;
    xml) set -- --testsuite "XML Coding Standard Verification" "$@" ;;
    html) set -- --testsuite "HTML Static Code Analysis" "$@" ;;
    less) set -- --testsuite "Less Static Code Analysis" "$@" ;;
    graphql) set -- --testsuite "GraphQL Static Code Analysis" "$@" ;;
    integrity) set -- --testsuite "Code Integrity Tests" "$@" ;;
    legacy)
        configuration=phpunit-all.xml.dist
        set -- testsuite/Magento/Test/Legacy "$@"
        ;;
    *)
        echo "unknown suite: $suite" >&2
        exit 2
        ;;
esac

cd "$magento/dev/tests/static"
exec php -d memory_limit=-1 -d variables_order=EGPCS ../../../vendor/bin/phpunit --configuration "$configuration" --colors=never "$@"
