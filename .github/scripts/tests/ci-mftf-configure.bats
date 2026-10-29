#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    CALLS="$BATS_TEST_TMPDIR/php-calls"
    mkdir -p "$MAGENTO/dev/tests/acceptance" "$BIN"
    printf '#!/bin/sh\necho "$@" >> "%s"\n' "$CALLS" > "$BIN/php"
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-configure.sh"
    PATH="$BIN:$PATH"
}

@test "builds the MFTF project against the base URL and the backend name the install uses" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF "vendor/bin/mftf build:project" "$CALLS"
    grep -qF -- "--MAGENTO_BASE_URL=http://127.0.0.1:8080/" "$CALLS"
    grep -qF -- "--MAGENTO_BACKEND_NAME=backend" "$CALLS"
    grep -qF -- "--MAGENTO_ADMIN_USERNAME=admin" "$CALLS"
}

@test "gives the magentoCLI action room to finish a reindex" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF -- "--MAGENTO_CLI_WAIT_TIMEOUT=120" "$CALLS"
}

@test "does not ask Selenium 4 for the session list MFTF closes at the end of a suite" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF -- "--SELENIUM_CLOSE_ALL_SESSIONS=false" "$CALLS"
}

@test "creates the utils directory the command endpoint is copied into" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    [ -d "$MAGENTO/dev/tests/acceptance/utils" ]
}

@test "writes the admin password and the OTP secret the install registered" {
    MFTF_ADMIN_PASSWORD=secret1 MFTF_OTP_SECRET=JBSWY3DPEHPK3PXP run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qxF "magento/MAGENTO_ADMIN_PASSWORD=secret1" "$MAGENTO/dev/tests/acceptance/.credentials"
    grep -qxF "magento/tfa/OTP_SHARED_SECRET=JBSWY3DPEHPK3PXP" "$MAGENTO/dev/tests/acceptance/.credentials"
}

@test "requires the Magento directory and the base URL" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
}
