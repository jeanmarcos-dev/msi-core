#!/usr/bin/env bats

setup() {
    TEST_SH="$(command -v "${TEST_SH:-dash}")" || { echo "dash is required: install it or set TEST_SH" >&2; return 1; }
    REPO="$BATS_TEST_TMPDIR/repo"
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    mkdir -p "$REPO/.github/scripts" "$BATS_TEST_TMPDIR/bin" "$MAGENTO/dev/tests/integration"
    cp "$BATS_TEST_DIRNAME/../ci-integration-modules.sh" "$BATS_TEST_DIRNAME/../ci-integration.sh" "$REPO/.github/scripts/"
    cat > "$BATS_TEST_TMPDIR/bin/php" <<'STUB'
#!/bin/sh
case "$1" in
    -d)
        echo "phpunit $*" >> "$CALLS"
        for failing in ${FAILING_MODULES:-}; do
            case "$*" in
                *"\\$failing\\"*) exit 2 ;;
            esac
        done
        ;;
esac
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/php"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    cd "$REPO"
}

@test "runs every module as its own phpunit invocation with its own filter and JUnit report" {
    run "$TEST_SH" .github/scripts/ci-integration-modules.sh "$MAGENTO" "$BATS_TEST_TMPDIR/reports" InventoryCatalog InventorySales

    [ "$status" -eq 0 ]
    [ "$(grep -c '^phpunit ' "$CALLS")" -eq 2 ]
    grep -qF -- '--filter Magento\\InventoryCatalog\\Test\\ --log-junit '"$BATS_TEST_TMPDIR"'/reports/InventoryCatalog.xml' "$CALLS"
    grep -qF -- '--filter Magento\\InventorySales\\Test\\ --log-junit '"$BATS_TEST_TMPDIR"'/reports/InventorySales.xml' "$CALLS"
}

@test "creates the report directory and resolves it before the tests change directory" {
    cd "$BATS_TEST_TMPDIR"

    run "$TEST_SH" repo/.github/scripts/ci-integration-modules.sh "$MAGENTO" reports InventoryCatalog

    [ "$status" -eq 0 ]
    [ -d "$BATS_TEST_TMPDIR/reports" ]
    grep -qF -- "--log-junit $BATS_TEST_TMPDIR/reports/InventoryCatalog.xml" "$CALLS"
}

@test "makes a module that selects no tests fail" {
    run "$TEST_SH" .github/scripts/ci-integration-modules.sh "$MAGENTO" "$BATS_TEST_TMPDIR/reports" InventoryCatalog

    [ "$status" -eq 0 ]
    grep -qF -- '--fail-on-empty-test-suite' "$CALLS"
}

@test "keeps running the other modules when one fails and then fails naming it" {
    FAILING_MODULES="InventoryCatalog InventoryShipping" run "$TEST_SH" .github/scripts/ci-integration-modules.sh "$MAGENTO" "$BATS_TEST_TMPDIR/reports" InventoryCatalog InventorySales InventoryShipping InventoryIndexer

    [ "$status" -eq 1 ]
    [ "$(grep -c '^phpunit ' "$CALLS")" -eq 4 ]
    [[ "$output" == *"failed modules: InventoryCatalog InventoryShipping"* ]]
    [[ "$output" != *"failed modules:"*"InventorySales"* ]]
}

@test "reports a module as passed or failed as it finishes" {
    FAILING_MODULES="InventorySales" run "$TEST_SH" .github/scripts/ci-integration-modules.sh "$MAGENTO" "$BATS_TEST_TMPDIR/reports" InventoryCatalog InventorySales

    [[ "$output" == *"passed: InventoryCatalog"* ]]
    [[ "$output" == *"failed: InventorySales"* ]]
}

@test "does not mistake a module whose name starts with another module's name for it" {
    FAILING_MODULES="InventoryInStorePickupSales" run "$TEST_SH" .github/scripts/ci-integration-modules.sh "$MAGENTO" "$BATS_TEST_TMPDIR/reports" InventoryInStorePickup InventoryInStorePickupSales

    [ "$status" -eq 1 ]
    [[ "$output" == *"passed: InventoryInStorePickup"$'\n'* ]]
    [[ "$output" == *"failed modules: InventoryInStorePickupSales"* ]]
}

@test "requires at least one module" {
    run "$TEST_SH" .github/scripts/ci-integration-modules.sh "$MAGENTO" "$BATS_TEST_TMPDIR/reports"

    [ "$status" -eq 2 ]
    [[ "$output" == *"usage: ci-integration-modules.sh"* ]]
    [ ! -e "$CALLS" ]
}
