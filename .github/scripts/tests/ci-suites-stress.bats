#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    CALLS="$BATS_TEST_TMPDIR/php-calls"
    mkdir -p "$MAGENTO/bin" "$BIN"
    cat > "$BIN/php" <<'STUB'
#!/bin/sh
echo "MAGENTO_ROOT=$MAGENTO_ROOT $*" >> "$CALLS"
if [ -n "${FAIL_ON:-}" ]; then
    case "$*" in
        *"$FAIL_ON"*) exit 1 ;;
    esac
fi
STUB
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-suites-stress.sh"
    PATH="$BIN:$PATH"
    export CALLS
}

@test "runs the steps in order against the given Magento" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -eq 0 ]
    order=$(grep -oE 'topology|config:set|indexer:reindex|e2e|stress-worker|checkout-worker|cancel-orders|ledger' "$CALLS" | uniq | paste -sd' ')
    [ "$order" = "topology config:set indexer:reindex e2e stress-worker checkout-worker cancel-orders ledger" ]
    grep -q "MAGENTO_ROOT=$MAGENTO " "$CALLS"
}

@test "starts every worker of both concurrent phases" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$(grep -c 'stress-worker' "$CALLS")" -eq 8 ]
    [ "$(grep -c 'checkout-worker' "$CALLS")" -eq 6 ]
}

@test "fails when one stress worker reports errors, after every worker has finished" {
    FAIL_ON="stress-worker.php 3" run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
    [ "$(grep -c 'stress-worker' "$CALLS")" -eq 8 ]
    ! grep -q 'ledger' "$CALLS"
}

@test "fails when a checkout worker reports errors" {
    FAIL_ON="checkout-worker.php 2" run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
    ! grep -q 'cancel-orders' "$CALLS"
}

@test "fails when the e2e suite fails and goes no further" {
    FAIL_ON="e2e.php" run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
    ! grep -q 'stress-worker' "$CALLS"
}

@test "requires the Magento directory" {
    run sh "$SCRIPT"

    [ "$status" -ne 0 ]
}
