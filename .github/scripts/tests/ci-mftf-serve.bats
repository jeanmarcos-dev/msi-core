#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$MAGENTO/pub" "$BIN"
    printf '#!/bin/sh\necho "$@" > "%s/php-args"\necho "$MAGENTO_ROOT" > "%s/php-root"\n' "$BATS_TEST_TMPDIR" "$BATS_TEST_TMPDIR" > "$BIN/php"
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-serve.sh"
    PATH="$BIN:$PATH"
}

@test "starts the PHP server on the MFTF router and returns once Magento answers" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    grep -qF -- "-S 127.0.0.1:8099" "$BATS_TEST_TMPDIR/php-args"
    grep -qF "ci-mftf-router.php" "$BATS_TEST_TMPDIR/php-args"
}

@test "tells the router where Magento lives" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    [ "$(cat "$BATS_TEST_TMPDIR/php-root")" = "$MAGENTO" ]
}

@test "enables opcache for the CLI server, since every request bootstraps Magento" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    grep -qF -- "opcache.enable_cli=1" "$BATS_TEST_TMPDIR/php-args"
}

@test "rereads env.php on every request, since a test adds a downloadable domain by CLI and calls the API a second later" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    grep -qF -- "opcache.revalidate_freq=0" "$BATS_TEST_TMPDIR/php-args"
}

@test "fails with the server log when Magento never answers" {
    printf '#!/bin/sh\nexit 7\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    MFTF_SERVE_ATTEMPTS=2 run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 1 ]
    [[ "$output" == *"PHP server did not answer on port 8099"* ]]
}

@test "requires the Magento directory, the port and the log file" {
    run sh "$SCRIPT" "$MAGENTO" 8099

    [ "$status" -ne 0 ]
}
