#!/usr/bin/env bats

setup() {
    REPO="$BATS_TEST_TMPDIR/repo"
    OUT="$BATS_TEST_TMPDIR/out"
    mkdir -p "$REPO" "$OUT"
    cd "$REPO"
    git init --quiet --initial-branch=main
    git config user.email test@example.com
    git config user.name test
    mkdir -p InventoryApi/etc InventorySales docs
    echo a > InventoryApi/etc/module.xml
    echo a > InventorySales/Old.php
    echo a > InventorySales/Gone.php
    echo a > docs/guide.md
    git add -A
    git commit --quiet -m base
    BASE=$(git rev-parse HEAD)
    echo b >> InventoryApi/etc/module.xml
    echo new > InventorySales/New.php
    git rm --quiet InventorySales/Gone.php
    git mv InventorySales/Old.php InventorySales/Renamed.php
    git add -A
    git commit --quiet -m change
    SCRIPT="$BATS_TEST_DIRNAME/../ci-static-lists.sh"
}

@test "lists the files the change touched and leaves out the deleted ones" {
    run sh "$SCRIPT" "$BASE" "$OUT"

    [ "$status" -eq 0 ]
    [ "$(sort "$OUT/changed.txt")" = "InventoryApi/etc/module.xml
InventorySales/New.php
InventorySales/Renamed.php" ]
}

@test "lists the added files apart" {
    run sh "$SCRIPT" "$BASE" "$OUT"

    [ "$status" -eq 0 ]
    [ "$(cat "$OUT/added.txt")" = "InventorySales/New.php" ]
}

@test "lists every tracked file" {
    run sh "$SCRIPT" "$BASE" "$OUT"

    [ "$status" -eq 0 ]
    [ "$(wc -l < "$OUT/all.txt")" -eq 4 ]
    grep -qx "docs/guide.md" "$OUT/all.txt"
}

@test "writes empty change lists when nothing changed" {
    run sh "$SCRIPT" "$(git rev-parse HEAD)" "$OUT"

    [ "$status" -eq 0 ]
    [ -f "$OUT/changed.txt" ]
    [ ! -s "$OUT/changed.txt" ]
    [ ! -s "$OUT/added.txt" ]
}

@test "treats every file as added when there is no base to compare with" {
    run sh "$SCRIPT" 0000000000000000000000000000000000000000 "$OUT"

    [ "$status" -eq 0 ]
    [ "$(wc -l < "$OUT/added.txt")" -eq 4 ]
    cmp "$OUT/added.txt" "$OUT/changed.txt"
}

@test "treats every file as added when the base is empty" {
    run sh "$SCRIPT" "" "$OUT"

    [ "$status" -eq 0 ]
    [ "$(wc -l < "$OUT/added.txt")" -eq 4 ]
}

@test "fails without an output directory" {
    run sh "$SCRIPT" "$BASE"

    [ "$status" -ne 0 ]
    [[ "$output" == *"usage:"* ]]
}
