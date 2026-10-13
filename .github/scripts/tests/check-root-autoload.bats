#!/usr/bin/env bats

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    mkdir -p "$REPO/.github/scripts" "$REPO/ModA" "$REPO/ModB"
    [ ! -f "$BATS_TEST_DIRNAME/../check-root-autoload.sh" ] || cp "$BATS_TEST_DIRNAME/../check-root-autoload.sh" "$REPO/.github/scripts/"
    touch "$REPO/ModA/registration.php" "$REPO/ModB/registration.php"
    cat > "$REPO/composer.json" <<'JSON'
{
    "autoload": {
        "files": ["ModA/registration.php", "ModB/registration.php"],
        "psr-4": {"Magento\\ModA\\": "ModA/", "Magento\\ModB\\": "ModB/"}
    }
}
JSON
    cd "$REPO"
    git init -q -b main
}

@test "passes when every module has both entries and none is extra" {
    run sh .github/scripts/check-root-autoload.sh

    [ "$status" -eq 0 ]
}

@test "fails and names both entries when a module is missing from the autoload" {
    mkdir NewModule
    touch NewModule/registration.php

    run sh .github/scripts/check-root-autoload.sh

    [ "$status" -ne 0 ]
    [[ "$output" == *"NewModule/registration.php"* ]]
    [[ "$output" == *'Magento\\NewModule\\'* ]]
}

@test "fails and names the entries that point to a directory without registration.php" {
    jq '.autoload.files += ["Ghost/registration.php"] | .autoload["psr-4"]["Magento\\Ghost\\"] = "Ghost/"' composer.json > c.json
    mv c.json composer.json

    run sh .github/scripts/check-root-autoload.sh

    [ "$status" -ne 0 ]
    [[ "$output" == *"Ghost/registration.php"* ]]
    [[ "$output" == *'Magento\\Ghost\\'* ]]
}

@test "fails when only the psr-4 entry is missing" {
    jq 'del(.autoload["psr-4"]["Magento\\ModB\\"])' composer.json > c.json
    mv c.json composer.json

    run sh .github/scripts/check-root-autoload.sh

    [ "$status" -ne 0 ]
    [[ "$output" == *'Magento\\ModB\\'* ]]
    [[ "$output" != *"ModB/registration.php"* ]]
}

stub_failing_comparators() {
    STUBS="$BATS_TEST_TMPDIR/stubs"
    mkdir -p "$STUBS"
    for tool in git php sort tr sed dirname printf mktemp cat rm wc; do
        ln -s "$(command -v "$tool")" "$STUBS/$tool"
    done
    for tool in grep comm; do
        printf '#!/bin/sh\nexit 2\n' > "$STUBS/$tool"
        chmod +x "$STUBS/$tool"
    done
}

@test "a failing comparison cannot pass silently" {
    stub_failing_comparators

    PATH="$STUBS" run /bin/sh .github/scripts/check-root-autoload.sh

    [ "$status" -ne 0 ]
    [[ "$output" != *"root autoload: ok"* ]]
}

@test "an unreadable composer.json cannot pass silently" {
    printf '{ not json' > composer.json

    run sh .github/scripts/check-root-autoload.sh

    [ "$status" -ne 0 ]
    [[ "$output" != *"root autoload: ok"* ]]
}
