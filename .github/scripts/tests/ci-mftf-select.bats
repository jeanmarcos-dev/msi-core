#!/usr/bin/env bats

setup() {
    SOURCE="$BATS_TEST_TMPDIR/msi-core"
    GENERATED="$BATS_TEST_TMPDIR/_generated"
    mkdir -p "$SOURCE/InventoryAdminUi/Test/Mftf/Test" "$SOURCE/InventoryCatalog/Test/Mftf/Test" "$GENERATED/default" "$GENERATED/InventoryMultiModeSuite" "$GENERATED/PaypalSuite"
    cat > "$SOURCE/InventoryAdminUi/Test/Mftf/Test/AdminTest.xml" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<tests>
    <test name="AdminAlphaTest">
        <annotations>
            <group value="msi"/>
            <group value="multi_mode"/>
        </annotations>
    </test>
    <test name="AdminBetaTest">
        <annotations>
            <group value="msi"/>
        </annotations>
    </test>
    <test name="AdminGammaTest">
        <annotations>
            <group value="multi_mode"/>
        </annotations>
    </test>
</tests>
XML
    cat > "$SOURCE/InventoryCatalog/Test/Mftf/Test/CatalogTest.xml" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<tests>
    <test name="StorefrontDeltaTest">
        <annotations>
            <group value="msi"/>
        </annotations>
    </test>
</tests>
XML
    touch "$GENERATED/InventoryMultiModeSuite/AdminAlphaTestCest.php" \
        "$GENERATED/InventoryMultiModeSuite/AdminGammaTestCest.php" \
        "$GENERATED/default/AdminBetaTestCest.php" \
        "$GENERATED/default/StorefrontDeltaTestCest.php" \
        "$GENERATED/default/CoreCheckoutTestCest.php" \
        "$GENERATED/PaypalSuite/CorePaypalTestCest.php"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-select.php"
}

@test "selects the msi-core tests only, keeping each one in the suite MFTF generated it in" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 1 1

    [ "$status" -eq 0 ]
    [ "$output" = "$(printf '%s\n' AdminBetaTest StorefrontDeltaTest InventoryMultiModeSuite:AdminAlphaTest InventoryMultiModeSuite:AdminGammaTest)" ]
}

@test "gives every test to exactly one shard" {
    for shard in 1 2 3; do
        php "$SCRIPT" "$GENERATED" "$SOURCE" "$shard" 3
    done | sed 's/^.*://' | sort > "$BATS_TEST_TMPDIR/assigned"

    [ "$(cat "$BATS_TEST_TMPDIR/assigned")" = "$(printf '%s\n' AdminAlphaTest AdminBetaTest AdminGammaTest StorefrontDeltaTest)" ]
}

@test "spreads the tests of one module over the shards" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 1 2

    [ "$status" -eq 0 ]
    [[ "$output" == *"AdminAlphaTest"* ]]
    [[ "$output" != *"AdminBetaTest"* ]]
    [[ "$output" == *"AdminGammaTest"* ]]
}

@test "keeps only the tests named in the list" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 1 1 --tests "AdminBetaTest, AdminGammaTest"

    [ "$status" -eq 0 ]
    [ "$output" = "$(printf '%s\n' AdminBetaTest InventoryMultiModeSuite:AdminGammaTest)" ]
}

@test "keeps only the tests of an MFTF group" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 1 1 --group multi_mode

    [ "$status" -eq 0 ]
    [ "$output" = "$(printf '%s\n' InventoryMultiModeSuite:AdminAlphaTest InventoryMultiModeSuite:AdminGammaTest)" ]
}

@test "names a test once per suite that MFTF generated it in" {
    touch "$GENERATED/PaypalSuite/AdminGammaTestCest.php"

    run php "$SCRIPT" "$GENERATED" "$SOURCE" 1 1 --tests AdminGammaTest

    [ "$status" -eq 0 ]
    [ "$output" = "$(printf '%s\n' InventoryMultiModeSuite:AdminGammaTest PaypalSuite:AdminGammaTest)" ]
}

@test "fails when a requested test is not a msi-core test that MFTF generated" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 1 1 --tests "AdminBetaTest,CoreCheckoutTest,NoSuchTest"

    [ "$status" -eq 1 ]
    [[ "$output" == *"CoreCheckoutTest"* ]]
    [[ "$output" == *"NoSuchTest"* ]]
}

@test "prints an empty selection when the shard has nothing to run" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 5 5

    [ "$status" -eq 0 ]
    [ -z "$output" ]
}

@test "rejects a shard outside the range" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 4 3

    [ "$status" -eq 2 ]
}

@test "requires the generated directory, the msi-core checkout, the shard and the shard count" {
    run php "$SCRIPT" "$GENERATED" "$SOURCE" 1

    [ "$status" -eq 2 ]
}
