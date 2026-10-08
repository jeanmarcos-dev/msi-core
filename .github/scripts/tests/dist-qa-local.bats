#!/usr/bin/env bats

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    mkdir -p "$REPO/.github/scripts"
    cp "$BATS_TEST_DIRNAME/../dist-qa-local.sh" "$REPO/.github/scripts/"
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

    run sh .github/scripts/dist-qa-local.sh main

    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed PHP files. Nothing to check."* ]]
}

@test "passes when the only changed PHP file was deleted and the deletion is uncommitted" {
    echo '<?php' > Gone.php
    git add Gone.php
    git commit -qm add
    rm Gone.php

    run sh .github/scripts/dist-qa-local.sh main

    [ "$status" -eq 0 ]
    [[ "$output" == *"No changed PHP files. Nothing to check."* ]]
}
