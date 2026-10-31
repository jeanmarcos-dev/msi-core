#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    mkdir -p "$MAGENTO/dev/tests/static" "$BATS_TEST_TMPDIR/bin"
    cat > "$BATS_TEST_TMPDIR/bin/php" <<'STUB'
#!/bin/sh
{ printf 'php'; printf ' [%s]' "$@"; echo; } >> "$CALLS"
case "$*" in
    *phpunit*) exit "${PHPUNIT_STATUS:-0}" ;;
    *) exit "${BASELINE_STATUS:-0}" ;;
esac
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/php"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    BASELINE="$BATS_TEST_TMPDIR/baseline.txt"
    : > "$BASELINE"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-static-integrity.sh"
}

@test "runs the integrity suite and has it write a JUnit report" {
    run sh "$SCRIPT" "$MAGENTO" "$BASELINE"

    [ "$status" -eq 0 ]
    grep -qF "[--testsuite] [Code Integrity Tests] [--log-junit] [$MAGENTO/var/integrity-junit.xml]" "$CALLS"
}

@test "compares the report with the baseline, handing over PHPUnit's exit status" {
    PHPUNIT_STATUS=2 run sh "$SCRIPT" "$MAGENTO" "$BASELINE"

    grep -qE "^php \[.*ci-static-baseline.php\] \[$MAGENTO/var/integrity-junit.xml\] \[$BASELINE\] \[$MAGENTO\] \[2\]\$" "$CALLS"
}

@test "passes when only baselined failures remain, even though PHPUnit failed" {
    PHPUNIT_STATUS=2 BASELINE_STATUS=0 run sh "$SCRIPT" "$MAGENTO" "$BASELINE"

    [ "$status" -eq 0 ]
}

@test "fails when the baseline check finds a new failure" {
    PHPUNIT_STATUS=2 BASELINE_STATUS=1 run sh "$SCRIPT" "$MAGENTO" "$BASELINE"

    [ "$status" -eq 1 ]
}

@test "fails without the two arguments" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
    [[ "$output" == *"usage:"* ]]
}
