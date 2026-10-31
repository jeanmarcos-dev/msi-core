#!/usr/bin/env bats

setup() {
    RESULTS="$BATS_TEST_TMPDIR/allure-results"
    mkdir -p "$RESULTS/aaa-attachment"
    result() {
        printf '{"uuid":"%s","fullName":"%s","status":"%s","start":%s,"stop":%s,"statusDetails":%s}' "$1" "$2" "$3" "$4" "$5" "$6" > "$RESULTS/$1-result.json"
    }
    result one 'Magento\\AcceptanceTest\\_default\\Backend\\AdminAlphaTestCest::AdminAlphaTest' passed 1000000 1030000 null
    result two 'Magento\\AcceptanceTest\\_InventoryMultiModeSuite\\Backend\\AdminBetaTestCest::AdminBetaTest' failed 1030000 1100500 '{"message":"Element not found"}'
    result three 'Magento\\AcceptanceTest\\_default\\Backend\\StorefrontGammaTestCest::StorefrontGammaTest' broken 1100500 1110000 '{"message":"Unable to get OTP"}'
    printf '{"uuid":"hook","name":"suite hook","status":"passed","start":1,"stop":2}' > "$RESULTS/hook-result.json"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-summary.php"
}

@test "counts the tests by status and ignores the entries that are not tests" {
    run php "$SCRIPT" "$RESULTS"

    [ "$status" -eq 0 ]
    [[ "$output" == *"3 tests: 1 passed, 1 failed, 1 broken, 0 skipped"* ]]
}

@test "lists each test with its suite, its status and its duration in seconds" {
    run php "$SCRIPT" "$RESULTS"

    [ "$status" -eq 0 ]
    [[ "$output" == *"| AdminAlphaTest | default | passed | 30.0 |"* ]]
    [[ "$output" == *"| AdminBetaTest | InventoryMultiModeSuite | failed | 70.5 |"* ]]
}

@test "puts the tests that did not pass first, with the reason" {
    run php "$SCRIPT" "$RESULTS"

    [ "$status" -eq 0 ]
    beta=$(printf '%s\n' "$output" | grep -n "AdminBetaTest" | head -1 | cut -d: -f1)
    alpha=$(printf '%s\n' "$output" | grep -n "AdminAlphaTest" | head -1 | cut -d: -f1)
    [ "$beta" -lt "$alpha" ]
    [[ "$output" == *"Element not found"* ]]
    [[ "$output" == *"Unable to get OTP"* ]]
}

@test "reports the total and the mean duration" {
    run php "$SCRIPT" "$RESULTS"

    [ "$status" -eq 0 ]
    [[ "$output" == *"Total 110.0 s, mean 36.7 s per test"* ]]
}

@test "says so when no test ran" {
    mkdir -p "$BATS_TEST_TMPDIR/empty"

    run php "$SCRIPT" "$BATS_TEST_TMPDIR/empty"

    [ "$status" -eq 0 ]
    [[ "$output" == *"No MFTF result found"* ]]
}

@test "requires the results directory" {
    run php "$SCRIPT"

    [ "$status" -eq 2 ]
}

@test "counts a retried test once, by its last result" {
    result four 'Magento\\AcceptanceTest\\_InventoryMultiModeSuite\\Backend\\AdminBetaTestCest::AdminBetaTest' passed 1200000 1230000 null

    run php "$SCRIPT" "$RESULTS"

    [ "$status" -eq 0 ]
    [[ "$output" == *"3 tests: 2 passed, 0 failed, 1 broken, 0 skipped"* ]]
    [[ "$output" == *"| AdminBetaTest | InventoryMultiModeSuite | passed | 30.0 |"* ]]
    [[ "$output" != *"Element not found"* ]]
}
