#!/usr/bin/env bats

setup() {
    INTEGRATION="$BATS_TEST_TMPDIR/magento/dev/tests/integration"
    mkdir -p "$INTEGRATION/etc"
    cat > "$INTEGRATION/etc/install-config-mysql.php.dist" <<'PHP'
<?php
return [
    'db-host' => 'localhost',
    'db-user' => 'root',
    'db-password' => '123123q',
    'db-name' => 'magento_integration_tests',
    'search-engine' => 'opensearch',
    'opensearch-host' => 'localhost',
    'opensearch-port' => 9200,
    'admin-user' => \Magento\TestFramework\Bootstrap::ADMIN_NAME,
    'amqp-host' => 'localhost',
    'amqp-port' => '5672',
    'consumers-wait-for-messages' => '0',
];
PHP
    cat > "$INTEGRATION/phpunit.xml.dist" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="./framework/bootstrap.php">
    <testsuites>
        <testsuite name="Magento Integration Tests Real Suite">
            <directory>testsuite</directory>
        </testsuite>
    </testsuites>
    <php>
        <const name="TESTS_CLEANUP" value="enabled"/>
    </php>
</phpunit>
XML
    SCRIPT="$BATS_TEST_DIRNAME/../ci-integration-config.php"
}

@test "points the install at the configured database and OpenSearch and drops RabbitMQ" {
    DB_HOST=mysql DB_PASSWORD=secret OPENSEARCH_HOST=search run php "$SCRIPT" "$INTEGRATION" /checkout

    [ "$status" -eq 0 ]
    config="$INTEGRATION/etc/install-config-mysql.php"
    grep -qF "'db-host' => 'mysql'," "$config"
    grep -qF "'db-password' => 'secret'," "$config"
    grep -qF "'opensearch-host' => 'search'," "$config"
    grep -qF "'opensearch-port' => 9200," "$config"
    grep -qF "'admin-user' => \\Magento\\TestFramework\\Bootstrap::ADMIN_NAME," "$config"
    grep -qF "'consumers-wait-for-messages' => '0'," "$config"
    ! grep -q "amqp-" "$config"
}

@test "runs only the msi-core integration tests and keeps the framework constants" {
    run php "$SCRIPT" "$INTEGRATION" /checkout/

    [ "$status" -eq 0 ]
    [ "$(grep -c '<testsuite ' "$INTEGRATION/phpunit.xml")" -eq 1 ]
    grep -qF '<testsuite name="msi-core integration">' "$INTEGRATION/phpunit.xml"
    grep -qF '<directory suffix="Test.php">/checkout/*/Test/Integration</directory>' "$INTEGRATION/phpunit.xml"
    grep -qF '<const name="TESTS_CLEANUP" value="enabled"/>' "$INTEGRATION/phpunit.xml"
}

@test "fails when the install template no longer has a setting it must override" {
    sed -i "/'db-name'/d" "$INTEGRATION/etc/install-config-mysql.php.dist"

    run php "$SCRIPT" "$INTEGRATION" /checkout

    [ "$status" -eq 1 ]
    [[ "$output" == *"has no 'db-name' entry"* ]]
}
