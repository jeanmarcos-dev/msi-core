#!/usr/bin/env bats

setup() {
    TEST_SH="$(command -v "${TEST_SH:-dash}")" || { echo "dash is required: install it or set TEST_SH" >&2; return 1; }
    REPO="$BATS_TEST_TMPDIR/repo"
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    SPECS=dev/tests/js/jasmine/tests/app/code
    mkdir -p "$REPO/.github/scripts" "$MAGENTO/$SPECS/Magento/Catalog" "$BATS_TEST_TMPDIR/bin" "$BATS_TEST_TMPDIR/pkg/package"
    cp "$BATS_TEST_DIRNAME/../ci-jasmine.sh" "$REPO/.github/scripts/"
    touch "$MAGENTO/Gruntfile.js.sample" "$MAGENTO/package.json.sample" "$MAGENTO/$SPECS/Magento/Catalog/own.test.js"
    touch "$BATS_TEST_TMPDIR/pkg/package/package.json"
    tar -czf "$BATS_TEST_TMPDIR/jasmine-core.tgz" -C "$BATS_TEST_TMPDIR/pkg" package
    cat > "$BATS_TEST_TMPDIR/bin/npm" <<'STUB'
#!/bin/sh
printf 'npm %s\n' "$*" >> "$CALLS"
case "$1" in
    pack) cp "$TARBALL" "$PWD/jasmine-core-0.tgz"; echo jasmine-core-0.tgz ;;
    install) mkdir -p node_modules/grunt-contrib-jasmine ;;
esac
STUB
    cat > "$BATS_TEST_TMPDIR/bin/npx" <<'STUB'
#!/bin/sh
printf 'npx %s\n' "$*" >> "$CALLS"
exit "${NPX_STATUS:-0}"
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/npm" "$BATS_TEST_TMPDIR/bin/npx"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    export TARBALL="$BATS_TEST_TMPDIR/jasmine-core.tgz"
    mkdir -p "$REPO/$SPECS/Magento/InventoryStockVisualizer/frontend/js" "$REPO/$SPECS/Magento/InventoryConfigurableProductAdminUi/adminhtml/js"
    touch "$REPO/$SPECS/Magento/InventoryStockVisualizer/frontend/js/own.test.js"
    cd "$REPO"
}

@test "runs grunt spec for the theme against the specs of the modules that have a test file" {
    run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO" luma

    [ "$status" -eq 0 ]
    grep -qxF "npx grunt spec:luma --file=$SPECS/Magento/InventoryStockVisualizer/**/*.test.js" "$CALLS"
}

@test "does not select the specs that ship with Magento" {
    run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO"

    [ "$status" -eq 0 ]
    ! grep -q 'Catalog' "$CALLS"
}

@test "brace-expands the module list when several modules have specs" {
    touch "$REPO/$SPECS/Magento/InventoryConfigurableProductAdminUi/adminhtml/js/other.test.js"

    run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO"

    [ "$status" -eq 0 ]
    grep -qxF "npx grunt spec:luma --file=$SPECS/Magento/{InventoryConfigurableProductAdminUi,InventoryStockVisualizer}/**/*.test.js" "$CALLS"
}

@test "puts the specs and Magento's grunt files in place before running" {
    run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO"

    [ "$status" -eq 0 ]
    [ -f "$MAGENTO/Gruntfile.js" ]
    [ -f "$MAGENTO/package.json" ]
    [ -f "$MAGENTO/$SPECS/Magento/InventoryStockVisualizer/frontend/js/own.test.js" ]
}

@test "pins the jasmine-core that Magento's spec runner template supports" {
    run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO"

    [ "$status" -eq 0 ]
    grep -qE '^npm pack jasmine-core@5\.' "$CALLS"
    [ -f "$MAGENTO/node_modules/grunt-contrib-jasmine/.jasmine/latest/package.json" ]
}

@test "installs the node modules before running grunt" {
    run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO"

    [ "$status" -eq 0 ]
    [ "$(grep -n '^npm install' "$CALLS" | cut -d: -f1)" -lt "$(grep -n '^npx grunt' "$CALLS" | cut -d: -f1)" ]
}

@test "fails when the repository has no spec" {
    rm "$REPO/$SPECS/Magento/InventoryStockVisualizer/frontend/js/own.test.js"

    run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO"

    [ "$status" -ne 0 ]
    ! grep -q '^npx' "$CALLS"
}

@test "fails when grunt reports a failing spec" {
    NPX_STATUS=3 run "$TEST_SH" .github/scripts/ci-jasmine.sh "$MAGENTO"

    [ "$status" -eq 3 ]
}
