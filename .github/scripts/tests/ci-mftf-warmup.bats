#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$MAGENTO/bin" "$BIN"
    printf '#!/bin/sh\necho "$PWD $@" >> "%s/php-calls"\n' "$BATS_TEST_TMPDIR" > "$BIN/php"
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-warmup.sh"
    PATH="$BIN:$PATH"
}

@test "deploys the static files of the Luma storefront and the backend admin in en_US" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -eq 0 ]
    grep -qF "bin/magento setup:static-content:deploy" "$BATS_TEST_TMPDIR/php-calls"
    grep -qF -- "-f" "$BATS_TEST_TMPDIR/php-calls"
    grep -qF "en_US" "$BATS_TEST_TMPDIR/php-calls"
    grep -qF -- "--area frontend" "$BATS_TEST_TMPDIR/php-calls"
    grep -qF -- "--area adminhtml" "$BATS_TEST_TMPDIR/php-calls"
    grep -qF -- "--theme Magento/luma" "$BATS_TEST_TMPDIR/php-calls"
    grep -qF -- "--theme Magento/backend" "$BATS_TEST_TMPDIR/php-calls"
}

@test "runs from the Magento directory without a memory limit" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -eq 0 ]
    grep -qF "$MAGENTO " "$BATS_TEST_TMPDIR/php-calls"
    grep -qF -- "memory_limit=-1" "$BATS_TEST_TMPDIR/php-calls"
}

@test "fails when the deploy fails" {
    printf '#!/bin/sh\nexit 3\n' > "$BIN/php"

    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
}

@test "requires the Magento directory" {
    run sh "$SCRIPT"

    [ "$status" -ne 0 ]
    [[ "$output" == *"usage: ci-mftf-warmup.sh <magento-dir>"* ]]
}
