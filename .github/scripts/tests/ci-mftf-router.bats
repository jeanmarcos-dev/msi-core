#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    mkdir -p "$MAGENTO/pub" "$MAGENTO/phpserver" "$MAGENTO/dev/tests/acceptance/utils"
    printf '<?php\necho "magento router " . $_SERVER["REQUEST_URI"];\n' > "$MAGENTO/phpserver/router.php"
    printf '<?php\necho "command from " . basename(getcwd());\n' > "$MAGENTO/dev/tests/acceptance/utils/command.php"
    ROUTER="$BATS_TEST_DIRNAME/../ci-mftf-router.php"
    PORT=$((20000 + RANDOM % 20000))
    MAGENTO_ROOT="$MAGENTO" php -S "127.0.0.1:$PORT" -t "$MAGENTO/pub" "$ROUTER" > "$BATS_TEST_TMPDIR/server.log" 2>&1 &
    SERVER=$!
    for _ in 1 2 3 4 5 6 7 8 9 10; do
        curl --silent --output /dev/null "http://127.0.0.1:$PORT/" && break
        sleep 0.3
    done
}

teardown() {
    kill "$SERVER" 2> /dev/null || true
}

@test "serves the MFTF command endpoint from its own directory, where bin/magento is a relative path" {
    run curl --silent --request POST "http://127.0.0.1:$PORT/dev/tests/acceptance/utils/command.php"

    [ "$status" -eq 0 ]
    [ "$output" = "command from utils" ]
}

@test "hands every other request to the router Magento ships" {
    run curl --silent "http://127.0.0.1:$PORT/backend/admin"

    [ "$status" -eq 0 ]
    [ "$output" = "magento router /backend/admin" ]
}

@test "ignores the query string when matching the command endpoint" {
    run curl --silent --request POST "http://127.0.0.1:$PORT/dev/tests/acceptance/utils/command.php?x=1"

    [ "$status" -eq 0 ]
    [ "$output" = "command from utils" ]
}

@test "refuses to start without the Magento root" {
    run env -u MAGENTO_ROOT php -r '$_SERVER["REQUEST_URI"] = "/"; require $argv[1];' "$ROUTER"

    [ "$status" -eq 1 ]
    [[ "$output" == *"MAGENTO_ROOT is not set"* ]]
}
