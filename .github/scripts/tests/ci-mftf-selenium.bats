#!/usr/bin/env bats

setup() {
    BIN="$BATS_TEST_TMPDIR/bin"
    CALLS="$BATS_TEST_TMPDIR/calls"
    mkdir -p "$BIN"
    printf '#!/bin/sh\necho "docker $*" >> "%s"\n' "$CALLS" > "$BIN/docker"
    printf '#!/bin/sh\nprintf "{\\"value\\":{\\"ready\\":true}}"\n' > "$BIN/curl"
    chmod +x "$BIN/docker" "$BIN/curl"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-selenium.sh"
    PATH="$BIN:$PATH"
}

@test "starts the Selenium image on the host network, so Chrome reaches Magento on loopback" {
    run sh "$SCRIPT" selenium/standalone-chromium:4.46.0

    [ "$status" -eq 0 ]
    grep -qF -- "--network host" "$CALLS"
    grep -qF "selenium/standalone-chromium:4.46.0" "$CALLS"
}

@test "allows the sessions the suite hooks and the tests hold at the same time" {
    run sh "$SCRIPT" selenium/standalone-chromium:4.46.0

    [ "$status" -eq 0 ]
    grep -qF -- "SE_NODE_MAX_SESSIONS=4" "$CALLS"
    grep -qF -- "SE_NODE_OVERRIDE_MAX_SESSIONS=true" "$CALLS"
}

@test "gives Chrome the shared memory it needs" {
    run sh "$SCRIPT" selenium/standalone-chromium:4.46.0

    [ "$status" -eq 0 ]
    grep -qF -- "--shm-size=2g" "$CALLS"
}

@test "returns once the grid reports ready" {
    run sh "$SCRIPT" selenium/standalone-chromium:4.46.0

    [ "$status" -eq 0 ]
}

@test "fails with the container log when the grid never gets ready" {
    printf '#!/bin/sh\nif [ "$1" = logs ]; then echo selenium-log; else echo "docker $*" >> "%s"; fi\n' "$CALLS" > "$BIN/docker"
    printf '#!/bin/sh\nprintf "{\\"value\\":{\\"ready\\":false}}"\n' > "$BIN/curl"

    MFTF_SELENIUM_ATTEMPTS=2 run sh "$SCRIPT" selenium/standalone-chromium:4.46.0

    [ "$status" -eq 1 ]
    [[ "$output" == *"Selenium did not become ready"* ]]
    [[ "$output" == *"selenium-log"* ]]
}

@test "requires the image" {
    run sh "$SCRIPT"

    [ "$status" -ne 0 ]
}
