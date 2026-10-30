#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$MAGENTO/bin" "$BIN"
    EXPECTED="$BATS_TEST_TMPDIR/expected.txt"
    cat > "$BIN/php" <<'STUB'
#!/bin/sh
case "$*" in
    *conformance.php*)
        cat "$CONFORMANCE_OUTPUT"
        exit "${CONFORMANCE_STATUS:-1}"
        ;;
esac
STUB
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-suites-conformance.sh"
    PATH="$BIN:$PATH"
    export CONFORMANCE_OUTPUT="$BATS_TEST_TMPDIR/output.txt"
    printf '  XX S14-config-write       source item status                 default=0 mirror=1\n' > "$EXPECTED"
}

@test "passes when the divergences are exactly the checked-in ones" {
    printf '  ok S01 a\n  XX S14-config-write       source item status                 default=0 mirror=1\nDIVERGENT conformance: 160/161 probes identical, 1 divergences\n' > "$CONFORMANCE_OUTPUT"

    run sh "$SCRIPT" "$MAGENTO" "$EXPECTED"

    [ "$status" -eq 0 ]
}

@test "fails and shows the new divergence" {
    printf '  XX S14-config-write       source item status                 default=0 mirror=1\n  XX S02-zero-qty           qty                                default=0 mirror=1\nDIVERGENT conformance: 159/161 probes identical, 2 divergences\n' > "$CONFORMANCE_OUTPUT"

    run sh "$SCRIPT" "$MAGENTO" "$EXPECTED"

    [ "$status" -ne 0 ]
    [[ "$output" == *"S02-zero-qty"* ]]
}

@test "fails when a checked-in divergence disappears, so the file never goes stale" {
    printf '  ok S14 a\nCONFORMANT conformance: 161/161 probes identical, 0 divergences\n' > "$CONFORMANCE_OUTPUT"

    CONFORMANCE_STATUS=0 run sh "$SCRIPT" "$MAGENTO" "$EXPECTED"

    [ "$status" -ne 0 ]
    [[ "$output" == *"S14-config-write"* ]]
}

@test "fails when the suite dies before it prints its verdict" {
    printf 'Fatal error: boom\n' > "$CONFORMANCE_OUTPUT"

    run sh "$SCRIPT" "$MAGENTO" "$EXPECTED"

    [ "$status" -ne 0 ]
}
