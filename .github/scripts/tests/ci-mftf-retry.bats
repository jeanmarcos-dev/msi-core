#!/usr/bin/env bats

setup() {
    FIRST="$BATS_TEST_TMPDIR/first"
    SECOND="$BATS_TEST_TMPDIR/second"
    mkdir -p "$FIRST" "$SECOND"
    result() {
        printf '{"uuid":"%s","fullName":"%s","status":"%s","start":%s,"stop":%s,"statusDetails":%s}' "$2" "$3" "$4" "$5" "$5" "$6" > "$1/$2-result.json"
    }
    result "$FIRST" one 'Magento\\AcceptanceTest\\_default\\Backend\\AdminAlphaTestCest::AdminAlphaTest' passed 1000 null
    result "$FIRST" two 'Magento\\AcceptanceTest\\_InventoryMultiModeSuite\\Backend\\AdminBetaTestCest::AdminBetaTest' failed 2000 '{"message":"Element not found\nsecond line"}'
    result "$FIRST" three 'Magento\\AcceptanceTest\\_default\\Backend\\StorefrontGammaTestCest::StorefrontGammaTest' broken 3000 '{"message":"Connection refused"}'
    result "$FIRST" four 'Magento\\AcceptanceTest\\_default\\Backend\\AdminDeltaTestCest::AdminDeltaTest' skipped 4000 null
    printf '{"uuid":"hook","name":"suite hook","status":"failed","start":1,"stop":2}' > "$FIRST/hook-result.json"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-retry.php"
}

@test "lists the failed and broken tests, naming the suite of each one but the default" {
    run php "$SCRIPT" failed "$FIRST"

    [ "$status" -eq 0 ]
    [ "$output" = "$(printf '%s\n' InventoryMultiModeSuite:AdminBetaTest StorefrontGammaTest)" ]
}

@test "lists nothing when no test failed" {
    run php "$SCRIPT" failed "$SECOND"

    [ "$status" -eq 0 ]
    [ -z "$output" ]
}

@test "reports as flaky the tests that failed and then passed, with the shard and the first error" {
    result "$SECOND" five 'Magento\\AcceptanceTest\\_InventoryMultiModeSuite\\Backend\\AdminBetaTestCest::AdminBetaTest' passed 5000 null
    result "$SECOND" six 'Magento\\AcceptanceTest\\_default\\Backend\\StorefrontGammaTestCest::StorefrontGammaTest' broken 6000 '{"message":"Connection refused"}'

    run php "$SCRIPT" flaky "$FIRST" "$SECOND" 7

    [ "$status" -eq 0 ]
    [[ "$output" == *"shard 7"* ]]
    [[ "$output" == *"| AdminBetaTest | InventoryMultiModeSuite | Element not found |"* ]]
    [[ "$output" != *"StorefrontGammaTest"* ]]
}

@test "reports nothing when no test passed on the retry" {
    run php "$SCRIPT" flaky "$FIRST" "$SECOND" 7

    [ "$status" -eq 0 ]
    [ -z "$output" ]
}

@test "requires a known mode and its directories" {
    run php "$SCRIPT" flaky "$FIRST"

    [ "$status" -eq 2 ]
}
