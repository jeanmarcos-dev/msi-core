#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$MAGENTO/pub" "$MAGENTO/phpserver" "$BIN"
    printf '#!/bin/sh\necho "$@" > "%s/php-args"\n' "$BATS_TEST_TMPDIR" > "$BIN/php"
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-webapi-serve.sh"
    PATH="$BIN:$PATH"
}

@test "starts the PHP server on the router and returns once Magento answers" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    grep -qF -- "-S 127.0.0.1:8099" "$BATS_TEST_TMPDIR/php-args"
    grep -qF "$MAGENTO/phpserver/router.php" "$BATS_TEST_TMPDIR/php-args"
}

@test "fails with the server log when Magento never answers" {
    printf '#!/bin/sh\nexit 7\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    WEBAPI_SERVE_ATTEMPTS=2 run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 1 ]
    [[ "$output" == *"did not answer on port 8099"* ]]
}

@test "requires the Magento directory, the port and the log file" {
    run sh "$SCRIPT" "$MAGENTO" 8099

    [ "$status" -ne 0 ]
}
