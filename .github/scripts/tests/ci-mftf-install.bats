#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    BIN="$BATS_TEST_TMPDIR/bin"
    CALLS="$BATS_TEST_TMPDIR/php-calls"
    mkdir -p "$MAGENTO/bin" "$BIN"
    printf '#!/bin/sh\necho "$@" >> "%s"\n' "$CALLS" > "$BIN/php"
    chmod +x "$BIN/php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-install.sh"
    PATH="$BIN:$PATH"
}

@test "installs Magento at the given base URL with rewrites on" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -F "setup:install" "$CALLS" | grep -qF -- "--base-url=http://127.0.0.1:8080/"
    grep -F "setup:install" "$CALLS" | grep -qF -- "--use-rewrites=1"
}

@test "keeps two-factor authentication enabled, since the MFTF login helpers expect it" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    ! grep -F "setup:install" "$CALLS" | grep -q "TwoFactorAuth"
}

@test "registers the shared secret the MFTF credentials carry for the admin user" {
    MFTF_OTP_SECRET=JBSWY3DPEHPK3PXP run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF "security:tfa:google:set-secret admin JBSWY3DPEHPK3PXP" "$CALLS"
}

@test "makes the Google authenticator the forced provider, so the admin login asks for the OTP the helpers fill" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF "config:set twofactorauth/general/force_providers google" "$CALLS"
}

@test "configures the admin the way the MFTF tests expect it" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF "config:set cms/wysiwyg/enabled disabled" "$CALLS"
    grep -qF "config:set admin/security/admin_account_sharing 1" "$CALLS"
    grep -qF "config:set admin/security/use_form_key 0" "$CALLS"
    grep -qF "config:set admin/captcha/enable 0" "$CALLS"
    grep -qF "config:set customer/captcha/enable 0" "$CALLS"
}

@test "stretches the admin API token past the longest group run" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF "config:set webapi/jwtauth/admin_expiration 2880" "$CALLS"
}

@test "updates the indexers on save and ends in developer mode" {
    run sh "$SCRIPT" "$MAGENTO" http://127.0.0.1:8080/

    [ "$status" -eq 0 ]
    grep -qF "indexer:set-mode realtime" "$CALLS"
    [ "$(tail -n 1 "$CALLS")" = "bin/magento deploy:mode:set developer --skip-compilation" ]
}

@test "requires the Magento directory and the base URL" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
}
