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

@test "caps the PHP memory at 2G by default" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    grep -qF -- "memory_limit=2G" "$BATS_TEST_TMPDIR/php-args"
}

@test "takes the PHP memory cap from WEBAPI_SERVE_MEMORY_LIMIT" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    WEBAPI_SERVE_MEMORY_LIMIT=512M run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    grep -qF -- "memory_limit=512M" "$BATS_TEST_TMPDIR/php-args"
}

@test "lets the server dump core" {
    [ "$(ulimit -H -c)" = "unlimited" ] || skip "hard core limit is not unlimited"
    printf '#!/bin/sh\nulimit -c > "%s/core-limit"\n' "$BATS_TEST_TMPDIR" > "$BIN/php"
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    [ "$(cat "$BATS_TEST_TMPDIR/core-limit")" = "unlimited" ]
}

@test "serves with the JIT disabled and OPcache left on" {
    printf '#!/bin/sh\nexit 0\n' > "$BIN/curl"
    chmod +x "$BIN/curl"

    run sh "$SCRIPT" "$MAGENTO" 8099 "$BATS_TEST_TMPDIR/server.log"

    [ "$status" -eq 0 ]
    sleep 1
    grep -qF -- "opcache.jit=disable" "$BATS_TEST_TMPDIR/php-args"
    ! grep -qF -- "opcache.enable=0" "$BATS_TEST_TMPDIR/php-args"
}
