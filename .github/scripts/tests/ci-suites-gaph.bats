#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    CALLS="$BATS_TEST_TMPDIR/php-calls"
    VERDICTS="$BATS_TEST_TMPDIR/verdicts"
    mkdir -p "$MAGENTO/var/suites" "$BIN"
    cat > "$BIN/php" <<'STUB'
#!/bin/sh
echo "$*" >> "$CALLS"
case "$*" in
    *gaph-check.php*)
        status=$(head -n 1 "$VERDICTS")
        sed -i 1d "$VERDICTS"
        exit "${status:-0}"
        ;;
esac
STUB
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-suites-gaph.sh"
    PATH="$BIN:$PATH"
    export CALLS VERDICTS SUITES_GAPH_WARMUP=0
}

@test "runs every round and passes when none oversold" {
    printf '0\n0\n0\n' > "$VERDICTS"

    run sh "$SCRIPT" "$MAGENTO" 3

    [ "$status" -eq 0 ]
    [ "$(grep -c 'gaph-setup' "$CALLS")" -eq 3 ]
    [ "$(grep -c 'gaph-worker' "$CALLS")" -eq 6 ]
    grep -q 'gaph-teardown' "$CALLS"
}

@test "fails when a round oversold, and still tears down" {
    printf '0\n1\n0\n' > "$VERDICTS"

    run sh "$SCRIPT" "$MAGENTO" 3

    [ "$status" -ne 0 ]
    [[ "$output" == *"oversold rounds: 1"* ]]
    grep -q 'gaph-teardown' "$CALLS"
}

@test "plays a round again when the workers never contended" {
    printf '3\n0\n0\n' > "$VERDICTS"

    run sh "$SCRIPT" "$MAGENTO" 2

    [ "$status" -eq 0 ]
    [ "$(grep -c 'gaph-setup' "$CALLS")" -eq 3 ]
}

@test "gives up on a round that never contends" {
    printf '3\n3\n3\n3\n' > "$VERDICTS"

    SUITES_GAPH_RETRIES=2 run sh "$SCRIPT" "$MAGENTO" 1

    [ "$status" -ne 0 ]
    [[ "$output" == *"no contention"* ]]
}

@test "requires the Magento directory and the number of rounds" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
}
