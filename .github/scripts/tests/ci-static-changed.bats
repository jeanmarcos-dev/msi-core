#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    TESTS="$MAGENTO/dev/tests/static/testsuite/Magento/Test"
    mkdir -p "$TESTS/_files" "$TESTS/Less/_files" "$TESTS/Html/_files" "$MAGENTO/app/code/Magento/InventoryApi" "$MAGENTO/app/code/Magento/InventorySales"
    touch "$MAGENTO/app/code/Magento/InventoryApi/registration.php" "$MAGENTO/app/code/Magento/InventorySales/registration.php"
    CHANGED="$BATS_TEST_TMPDIR/changed.txt"
    ADDED="$BATS_TEST_TMPDIR/added.txt"
    printf '%s\n' InventoryApi/etc/module.xml InventorySales/Model/Foo.php README.md .github/workflows/x.yml docs/a.md composer.json > "$CHANGED"
    printf '%s\n' InventorySales/Model/Foo.php README.md > "$ADDED"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-static-changed.sh"
}

@test "lists the changed module files where the static tests read them, under app/code/Magento" {
    run sh "$SCRIPT" "$MAGENTO" "$CHANGED" "$ADDED"

    [ "$status" -eq 0 ]
    [ "$(cat "$TESTS/_files/changed_files_msi-core.txt")" = "app/code/Magento/InventoryApi/etc/module.xml
app/code/Magento/InventorySales/Model/Foo.php" ]
}

@test "lists the added module files for the strict types check" {
    run sh "$SCRIPT" "$MAGENTO" "$CHANGED" "$ADDED"

    [ "$status" -eq 0 ]
    [ "$(cat "$TESTS/_files/changed_files_msi-core.added.txt")" = "app/code/Magento/InventorySales/Model/Foo.php" ]
}

@test "gives the Less and HTML checks the same changed files" {
    run sh "$SCRIPT" "$MAGENTO" "$CHANGED" "$ADDED"

    [ "$status" -eq 0 ]
    cmp "$TESTS/_files/changed_files_msi-core.txt" "$TESTS/Less/_files/changed_files_msi-core.txt"
    cmp "$TESTS/_files/changed_files_msi-core.txt" "$TESTS/Html/_files/changed_files_msi-core.txt"
}

@test "drops files that are not in a module" {
    run sh "$SCRIPT" "$MAGENTO" "$CHANGED" "$ADDED"

    [ "$status" -eq 0 ]
    ! grep -q "README\|workflows\|docs\|composer" "$TESTS/_files/changed_files_msi-core.txt"
}

@test "writes empty lists, so the static tests do not fall back to git diff, when nothing changed" {
    : > "$CHANGED"
    : > "$ADDED"

    run sh "$SCRIPT" "$MAGENTO" "$CHANGED" "$ADDED"

    [ "$status" -eq 0 ]
    [ -f "$TESTS/_files/changed_files_msi-core.txt" ]
    [ ! -s "$TESTS/_files/changed_files_msi-core.txt" ]
    [ -f "$TESTS/_files/changed_files_msi-core.added.txt" ]
}

@test "replaces lists left by an earlier run" {
    echo stale > "$TESTS/_files/changed_files_old.txt"

    run sh "$SCRIPT" "$MAGENTO" "$CHANGED" "$ADDED"

    [ "$status" -eq 0 ]
    [ ! -e "$TESTS/_files/changed_files_old.txt" ]
}

@test "fails without the two lists" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
    [[ "$output" == *"usage:"* ]]
}
