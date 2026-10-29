#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    mkdir -p "$MAGENTO/dev/tests/static" "$BATS_TEST_TMPDIR/bin"
    cat > "$BATS_TEST_TMPDIR/bin/php" <<'STUB'
#!/bin/sh
{ printf 'php'; printf ' [%s]' "$@"; echo " in $(pwd)"; } >> "$CALLS"
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/php"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-static.sh"
    STATIC="$MAGENTO/dev/tests/static"
}

@test "runs strict types on its own from the static tests directory" {
    run sh "$SCRIPT" "$MAGENTO" strict-types

    [ "$status" -eq 0 ]
    grep -qF "php [-d] [memory_limit=-1] [-d] [variables_order=EGPCS] [../../../vendor/bin/phpunit] [--configuration] [phpunit.xml.dist] [--colors=never] [--filter] [testStrictTypes] in $STATIC" "$CALLS"
}

@test "runs PHPStan on its own" {
    run sh "$SCRIPT" "$MAGENTO" phpstan

    [ "$status" -eq 0 ]
    grep -qF -- "[--filter] [testPhpStan] in $STATIC" "$CALLS"
}

@test "runs each coding standard suite by the name upstream gives it" {
    run sh "$SCRIPT" "$MAGENTO" xml
    run sh "$SCRIPT" "$MAGENTO" html
    run sh "$SCRIPT" "$MAGENTO" less
    run sh "$SCRIPT" "$MAGENTO" graphql

    grep -qF -- '[--testsuite] [XML Coding Standard Verification]' "$CALLS"
    grep -qF -- '[--testsuite] [HTML Static Code Analysis]' "$CALLS"
    grep -qF -- '[--testsuite] [Less Static Code Analysis]' "$CALLS"
    grep -qF -- '[--testsuite] [GraphQL Static Code Analysis]' "$CALLS"
}

@test "runs the integrity suite" {
    run sh "$SCRIPT" "$MAGENTO" integrity

    [ "$status" -eq 0 ]
    grep -qF -- '[--configuration] [phpunit.xml.dist] [--colors=never] [--testsuite] [Code Integrity Tests]' "$CALLS"
}

@test "runs the legacy suite from the configuration that lists every directory" {
    run sh "$SCRIPT" "$MAGENTO" legacy

    [ "$status" -eq 0 ]
    grep -qF -- '[--configuration] [phpunit-all.xml.dist] [--colors=never] [testsuite/Magento/Test/Legacy]' "$CALLS"
}

@test "passes the extra arguments to PHPUnit" {
    run sh "$SCRIPT" "$MAGENTO" integrity --log-junit report.xml

    [ "$status" -eq 0 ]
    grep -qF -- '[--testsuite] [Code Integrity Tests] [--log-junit] [report.xml]' "$CALLS"
}

@test "rejects an unknown suite before running anything" {
    run sh "$SCRIPT" "$MAGENTO" mess

    [ "$status" -eq 2 ]
    [[ "$output" == *"unknown suite: mess"* ]]
    [ ! -e "$CALLS" ]
}
