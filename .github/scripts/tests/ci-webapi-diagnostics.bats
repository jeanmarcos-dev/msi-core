#!/usr/bin/env bats

setup() {
    BIN="$BATS_TEST_TMPDIR/bin"
    OUT="$BATS_TEST_TMPDIR/out"
    CORES="$BATS_TEST_TMPDIR/cores"
    mkdir -p "$BIN" "$CORES"
    for tool in dmesg journalctl ps free; do
        printf '#!/bin/sh\necho "%s output"\n' "$tool" > "$BIN/$tool"
        chmod +x "$BIN/$tool"
    done
    SCRIPT="$BATS_TEST_DIRNAME/../ci-webapi-diagnostics.sh"
    PATH="$BIN:$PATH"
}

@test "collects the kernel log, the process list and the memory state" {
    run sh "$SCRIPT" "$OUT" "$CORES"

    [ "$status" -eq 0 ]
    grep -q "dmesg output" "$OUT/dmesg.txt"
    grep -q "journalctl output" "$OUT/journal-kernel.txt"
    grep -q "ps output" "$OUT/ps.txt"
    grep -q "free output" "$OUT/free.txt"
    [ -s "$OUT/meminfo.txt" ]
}

@test "copies the core files into the output directory" {
    echo core > "$CORES/core.php.123"

    run sh "$SCRIPT" "$OUT" "$CORES"

    [ "$status" -eq 0 ]
    [ -f "$OUT/cores/core.php.123" ]
}

@test "keeps going when the kernel log is not readable" {
    printf '#!/bin/sh\nexit 1\n' > "$BIN/dmesg"
    printf '#!/bin/sh\nexit 1\n' > "$BIN/journalctl"

    run sh "$SCRIPT" "$OUT" "$CORES"

    [ "$status" -eq 0 ]
    [ -f "$OUT/ps.txt" ]
}

@test "works without a cores directory" {
    run sh "$SCRIPT" "$OUT"

    [ "$status" -eq 0 ]
    [ -f "$OUT/free.txt" ]
}

@test "requires the output directory" {
    run sh "$SCRIPT"

    [ "$status" -ne 0 ]
}
