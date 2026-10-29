#!/usr/bin/env bats

setup() {
    API="$BATS_TEST_TMPDIR/magento/dev/tests/api-functional"
    mkdir -p "$API"
    for adapter in rest soap graphql; do
        cat > "$API/phpunit_$adapter.xml.dist" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="./framework/bootstrap.php">
    <testsuites>
        <testsuite name="Magento web API functional tests">
            <file>testsuite/Magento/WebApiTest.php</file>
        </testsuite>
        <testsuite name="Magento web API functional tests real suite">
            <directory>testsuite</directory>
        </testsuite>
    </testsuites>
    <php>
        <const name="TESTS_BASE_URL" value="http://magento.url"/>
        <const name="TESTS_WEBSERVICE_USER" value="admin"/>
        <const name="TESTS_CLEANUP" value="enabled"/>
    </php>
</phpunit>
XML
    done
    SCRIPT="$BATS_TEST_DIRNAME/../ci-webapi-config.php"
}

@test "runs only the msi-core Api tests for REST and SOAP" {
    for adapter in rest soap; do
        run php "$SCRIPT" "$API" /checkout "$adapter"

        [ "$status" -eq 0 ]
        file="$API/phpunit_$adapter.xml"
        [ "$(grep -c '<testsuite ' "$file")" -eq 1 ]
        grep -qF '<directory suffix="Test.php">/checkout/*/Test/Api</directory>' "$file"
        ! grep -q 'WebApiTest.php' "$file"
    done
}

@test "runs only the msi-core GraphQl tests for GraphQL" {
    run php "$SCRIPT" "$API" /checkout/ graphql

    [ "$status" -eq 0 ]
    file="$API/phpunit_graphql.xml"
    [ "$(grep -c '<testsuite ' "$file")" -eq 1 ]
    grep -qF '<directory suffix="Test.php">/checkout/*/Test/GraphQl</directory>' "$file"
}

@test "points the tests at the configured base URL and keeps the other constants" {
    WEBAPI_BASE_URL=http://127.0.0.1:9000 run php "$SCRIPT" "$API" /checkout rest

    [ "$status" -eq 0 ]
    file="$API/phpunit_rest.xml"
    grep -qF '<const name="TESTS_BASE_URL" value="http://127.0.0.1:9000"/>' "$file"
    grep -qF '<const name="TESTS_WEBSERVICE_USER" value="admin"/>' "$file"
    grep -qF '<const name="TESTS_CLEANUP" value="enabled"/>' "$file"
}

@test "defaults the base URL to the local PHP server" {
    run php "$SCRIPT" "$API" /checkout rest

    [ "$status" -eq 0 ]
    grep -qF '<const name="TESTS_BASE_URL" value="http://127.0.0.1:8080"/>' "$API/phpunit_rest.xml"
}

@test "rejects an unknown adapter" {
    run php "$SCRIPT" "$API" /checkout xmlrpc

    [ "$status" -eq 2 ]
    [[ "$output" == *usage* ]]
}

@test "fails when the base URL constant is missing" {
    sed -i '/TESTS_BASE_URL/d' "$API/phpunit_rest.xml.dist"

    run php "$SCRIPT" "$API" /checkout rest

    [ "$status" -eq 1 ]
}
