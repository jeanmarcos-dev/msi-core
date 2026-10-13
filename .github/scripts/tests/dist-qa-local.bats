#!/usr/bin/env bats

setup() {
    TEST_SH="$(command -v "${TEST_SH:-dash}")" || { echo "dash is required: install it or set TEST_SH" >&2; return 1; }
    REPO="$BATS_TEST_TMPDIR/repo"
    mkdir -p "$REPO/.github/scripts"
    cp "$BATS_TEST_DIRNAME/../dist-qa-local.sh" "$BATS_TEST_DIRNAME/../check-root-autoload.sh" "$REPO/.github/scripts/"
    echo '{}' > "$REPO/composer.json"
    cd "$REPO"
    git init -q -b main
    git config user.email qa@example.com
    git config user.name qa
    git add .
    git commit -qm base
    git switch -qc feature
}

@test "passes when the branch changes no PHP file" {
    echo readme > README.md
    git add README.md
    git commit -qm docs

    run "$TEST_SH" .github/scripts/dist-qa-local.sh main

    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed PHP files. Nothing to check."* ]]
}

@test "passes when the only changed PHP file was deleted and the deletion is uncommitted" {
    echo '<?php' > Gone.php
    git add Gone.php
    git commit -qm add
    rm Gone.php

    run "$TEST_SH" .github/scripts/dist-qa-local.sh main

    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed PHP files. Nothing to check."* ]]
}

@test "fails before checking PHP when a module is missing from the root composer autoload" {
    mkdir NewModule
    touch NewModule/registration.php

    run "$TEST_SH" .github/scripts/dist-qa-local.sh main

    [ "$status" -ne 0 ]
    [[ "$output" == *"NewModule/registration.php"* ]]
    [[ "$output" == *"dist QA (local): FAIL"* ]]
}
