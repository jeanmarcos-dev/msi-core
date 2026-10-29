#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    mkdir -p "$MAGENTO/app/code/Magento/InventoryApi" "$MAGENTO/app/code/Magento/InventorySales" "$BATS_TEST_TMPDIR/bin"
    touch "$MAGENTO/app/code/Magento/InventoryApi/registration.php" "$MAGENTO/app/code/Magento/InventorySales/registration.php" "$MAGENTO/package.json.sample"
    for tool in npm npx; do
        cat > "$BATS_TEST_TMPDIR/bin/$tool" <<'STUB'
#!/bin/sh
echo "$(basename "$0") $* in $(pwd)" >> "$CALLS"
STUB
        chmod +x "$BATS_TEST_TMPDIR/bin/$tool"
    done
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    LIST="$BATS_TEST_TMPDIR/list.txt"
    printf '%s\n' InventoryApi/view/adminhtml/web/js/a.js InventorySales/Model/B.php README.md docs/x.js InventorySales/view/frontend/web/js/c.js > "$LIST"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-static-js.sh"
}

@test "installs the Node packages Magento declares" {
    run sh "$SCRIPT" "$MAGENTO" "$LIST"

    [ "$status" -eq 0 ]
    [ -f "$MAGENTO/package.json" ]
    grep -qF "npm install --no-audit --no-fund in $MAGENTO" "$CALLS"
}

@test "lints the JavaScript of the modules with the Magento coding standard configuration" {
    run sh "$SCRIPT" "$MAGENTO" "$LIST"

    [ "$status" -eq 0 ]
    [ "$(tail -n 1 "$CALLS")" = "npx eslint -c vendor/magento/magento-coding-standard/eslint/eslint.config.mjs app/code/Magento/InventoryApi/view/adminhtml/web/js/a.js app/code/Magento/InventorySales/view/frontend/web/js/c.js in $MAGENTO" ]
}

@test "leaves out files that are not JavaScript of a module" {
    run sh "$SCRIPT" "$MAGENTO" "$LIST"

    [ "$status" -eq 0 ]
    ! grep -q "B.php\|README\|docs" "$CALLS"
}

@test "installs and lints nothing when no module JavaScript is listed" {
    printf '%s\n' InventorySales/Model/B.php > "$LIST"

    run sh "$SCRIPT" "$MAGENTO" "$LIST"

    [ "$status" -eq 0 ]
    [ ! -e "$CALLS" ]
}

@test "fails with the exit code of ESLint" {
    cat > "$BATS_TEST_TMPDIR/bin/npx" <<'STUB'
#!/bin/sh
exit 1
STUB

    run sh "$SCRIPT" "$MAGENTO" "$LIST"

    [ "$status" -eq 1 ]
}

@test "fails without the two arguments" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
    [[ "$output" == *"usage:"* ]]
}
