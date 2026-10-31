#!/usr/bin/env bats

setup() {
    TEST_SH="$(command -v "${TEST_SH:-dash}")" || { echo "dash is required: install it or set TEST_SH" >&2; return 1; }
    REPO="$BATS_TEST_TMPDIR/repo"
    mkdir -p "$REPO/InventoryApi/etc" "$REPO/InventoryApi/view/adminhtml/web/css"
    touch "$REPO/InventoryApi/registration.php"
    cd "$REPO"
    git init --quiet
    git config user.email ci@example.com
    git config user.name ci
    SCRIPT="$BATS_TEST_DIRNAME/../ci-static-copyright.sh"
    CHANGED="$BATS_TEST_TMPDIR/changed.txt"
    ADDED="$BATS_TEST_TMPDIR/added.txt"
    : > "$CHANGED"
    : > "$ADDED"
}

adobe_xml() {
    printf '<?xml version="1.0"?>\n<!--\n/**\n * Copyright 2024 Adobe\n * All Rights Reserved.\n */\n-->\n<config/>\n'
}

legacy_xml() {
    printf '<?xml version="1.0"?>\n<!--\n/**\n * Copyright © Magento, Inc. All rights reserved.\n * See COPYING.txt for license details.\n */\n-->\n<config/>\n'
}

own_xml() {
    printf '<?xml version="1.0"?>\n<!--\n/**\n * Copyright 2026 Jeanmarcos Juarez\n * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0\n */\n-->\n<config/>\n'
}

bare_xml() {
    printf '<?xml version="1.0"?>\n<config/>\n'
}

commit_base() {
    git add -A
    git commit --quiet --allow-empty -m "chore: base"
    BASE=$(git rev-parse HEAD)
}

check() {
    git add -A
    git commit --quiet --allow-empty -m "chore: head"
    run "$TEST_SH" "$SCRIPT" "$BASE" "$CHANGED" "$ADDED"
}

@test "accepts a new file that carries Adobe's notice" {
    commit_base
    adobe_xml > InventoryApi/etc/new.xml
    echo InventoryApi/etc/new.xml > "$CHANGED"
    echo InventoryApi/etc/new.xml > "$ADDED"

    check

    [ "$status" -eq 0 ]
}

@test "accepts a new file that carries the legacy Magento notice" {
    commit_base
    legacy_xml > InventoryApi/etc/new.xml
    echo InventoryApi/etc/new.xml > "$CHANGED"
    echo InventoryApi/etc/new.xml > "$ADDED"

    check

    [ "$status" -eq 0 ]
}

@test "accepts a new file that carries the msi-core header" {
    commit_base
    own_xml > InventoryApi/etc/new.xml
    echo InventoryApi/etc/new.xml > "$CHANGED"
    echo InventoryApi/etc/new.xml > "$ADDED"

    check

    [ "$status" -eq 0 ]
}

@test "rejects a new file that carries neither notice and names it" {
    commit_base
    bare_xml > InventoryApi/etc/new.xml
    echo InventoryApi/etc/new.xml > "$CHANGED"
    echo InventoryApi/etc/new.xml > "$ADDED"

    check

    [ "$status" -ne 0 ]
    [[ "$output" == *"InventoryApi/etc/new.xml"* ]]
}

@test "rejects a new file whose msi-core header has no SPDX line" {
    commit_base
    printf '<?xml version="1.0"?>\n<!--\n/**\n * Copyright 2026 Jeanmarcos Juarez\n */\n-->\n<config/>\n' > InventoryApi/etc/new.xml
    echo InventoryApi/etc/new.xml > "$CHANGED"
    echo InventoryApi/etc/new.xml > "$ADDED"

    check

    [ "$status" -ne 0 ]
}

@test "accepts a modified file that keeps Adobe's notice" {
    adobe_xml > InventoryApi/etc/di.xml
    commit_base
    printf '<extra/>\n' >> InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"

    check

    [ "$status" -eq 0 ]
}

@test "accepts a modified file that keeps the legacy Magento notice" {
    legacy_xml > InventoryApi/etc/di.xml
    commit_base
    printf '<extra/>\n' >> InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"

    check

    [ "$status" -eq 0 ]
}

@test "rejects a modified file whose Adobe notice was replaced by the msi-core header" {
    adobe_xml > InventoryApi/etc/di.xml
    commit_base
    own_xml > InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"

    check

    [ "$status" -ne 0 ]
    [[ "$output" == *"InventoryApi/etc/di.xml"* ]]
}

@test "rejects a modified file whose Adobe notice was removed" {
    adobe_xml > InventoryApi/etc/di.xml
    commit_base
    bare_xml > InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"

    check

    [ "$status" -ne 0 ]
}

@test "accepts a modified msi-core file" {
    own_xml > InventoryApi/etc/di.xml
    commit_base
    printf '<extra/>\n' >> InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"

    check

    [ "$status" -eq 0 ]
}

@test "rejects a modified file that had neither notice and still has none" {
    bare_xml > InventoryApi/etc/di.xml
    commit_base
    printf '<extra/>\n' >> InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"

    check

    [ "$status" -ne 0 ]
}

@test "ignores a deleted file, which is not in the changed list" {
    bare_xml > InventoryApi/etc/di.xml
    commit_base
    rm InventoryApi/etc/di.xml

    check

    [ "$status" -eq 0 ]
}

@test "ignores a listed file that no longer exists" {
    adobe_xml > InventoryApi/etc/di.xml
    commit_base
    rm InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"

    check

    [ "$status" -eq 0 ]
}

@test "treats a file missing from the base as new" {
    commit_base
    own_xml > InventoryApi/etc/moved.xml
    echo InventoryApi/etc/moved.xml > "$CHANGED"

    check

    [ "$status" -eq 0 ]
}

@test "treats every file as new when the base is the null commit" {
    adobe_xml > InventoryApi/etc/di.xml
    echo InventoryApi/etc/di.xml > "$CHANGED"
    BASE=0000000000000000000000000000000000000000

    check

    [ "$status" -eq 0 ]
}

@test "checks Less, CSS, HTML and GraphQL files the static tests cover" {
    commit_base
    printf 'body {}\n' > InventoryApi/view/adminhtml/web/css/_module.less
    printf '<div></div>\n' > InventoryApi/view/adminhtml/web/css/t.html
    printf 'type Query {}\n' > InventoryApi/etc/schema.graphqls
    printf '.a {}\n' > InventoryApi/view/adminhtml/web/css/a.css
    printf '%s\n' InventoryApi/view/adminhtml/web/css/_module.less InventoryApi/view/adminhtml/web/css/t.html InventoryApi/etc/schema.graphqls InventoryApi/view/adminhtml/web/css/a.css > "$CHANGED"
    cp "$CHANGED" "$ADDED"

    check

    [ "$status" -ne 0 ]
    [[ "$output" == *"_module.less"* ]]
    [[ "$output" == *"t.html"* ]]
    [[ "$output" == *"schema.graphqls"* ]]
    [[ "$output" == *"a.css"* ]]
}

@test "accepts a Less file with the msi-core header in line comments" {
    commit_base
    printf '// /**\n//  * Copyright 2026 Jeanmarcos Juarez\n//  * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0\n//  */\nbody {}\n' > InventoryApi/view/adminhtml/web/css/_module.less
    echo InventoryApi/view/adminhtml/web/css/_module.less > "$CHANGED"
    cp "$CHANGED" "$ADDED"

    check

    [ "$status" -eq 0 ]
}

@test "does not check types the static tests of this workflow do not cover" {
    commit_base
    printf '<?php\n' > InventoryApi/Foo.php
    printf '{}\n' > InventoryApi/composer.json
    printf '%s\n' InventoryApi/Foo.php InventoryApi/composer.json > "$CHANGED"
    cp "$CHANGED" "$ADDED"

    check

    [ "$status" -eq 0 ]
}

@test "does not check files outside a module" {
    commit_base
    mkdir -p docs
    bare_xml > docs/sample.xml
    echo docs/sample.xml > "$CHANGED"
    echo docs/sample.xml > "$ADDED"

    check

    [ "$status" -eq 0 ]
}

@test "reports every offending file, not only the first" {
    commit_base
    bare_xml > InventoryApi/etc/a.xml
    bare_xml > InventoryApi/etc/b.xml
    printf '%s\n' InventoryApi/etc/a.xml InventoryApi/etc/b.xml > "$CHANGED"
    cp "$CHANGED" "$ADDED"

    check

    [ "$status" -ne 0 ]
    [[ "$output" == *"etc/a.xml"* ]]
    [[ "$output" == *"etc/b.xml"* ]]
}

@test "passes when nothing changed" {
    commit_base

    check

    [ "$status" -eq 0 ]
}
