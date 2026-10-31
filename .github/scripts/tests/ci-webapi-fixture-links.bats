#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    SOURCE="$MAGENTO/vendor/jeanmarcos/msi-core"
    mkdir -p "$SOURCE/InventoryApi/Test/_files" "$SOURCE/InventorySales/Test/_files" "$SOURCE/InventoryCatalog/Api"
    touch "$SOURCE/InventoryApi/Test/_files/sources.php" "$SOURCE/InventoryApi/registration.php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-webapi-fixture-links.sh"
}

@test "exposes the Test directory of each module under app/code/Magento" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -eq 0 ]
    [ -f "$MAGENTO/app/code/Magento/InventoryApi/Test/_files/sources.php" ]
    [ -d "$MAGENTO/app/code/Magento/InventorySales/Test/_files" ]
}

@test "does not link modules that have no Test directory" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -eq 0 ]
    [ ! -e "$MAGENTO/app/code/Magento/InventoryCatalog" ]
}

@test "does not expose the module itself, so it cannot be registered twice" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -eq 0 ]
    [ ! -e "$MAGENTO/app/code/Magento/InventoryApi/registration.php" ]
}

@test "resolves the path the web API fixtures use" {
    sh "$SCRIPT" "$MAGENTO"
    mkdir -p "$MAGENTO/dev/tests/integration/testsuite"

    [ -f "$MAGENTO/dev/tests/integration/testsuite/../../../../app/code/Magento/InventoryApi/Test/_files/sources.php" ]
}

@test "can run twice" {
    sh "$SCRIPT" "$MAGENTO"

    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -eq 0 ]
    [ -f "$MAGENTO/app/code/Magento/InventoryApi/Test/_files/sources.php" ]
}

@test "fails without a Magento directory" {
    run sh "$SCRIPT"

    [ "$status" -ne 0 ]
}
