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
