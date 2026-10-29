#!/usr/bin/env bats

setup() {
    TEST_SH="$(command -v "${TEST_SH:-dash}")" || { echo "dash is required: install it or set TEST_SH" >&2; return 1; }
    REPO="$BATS_TEST_TMPDIR/repo"
    mkdir -p "$REPO/.github/scripts" "$BATS_TEST_TMPDIR/bin"
    cp "$BATS_TEST_DIRNAME/../ci-static-project.sh" "$REPO/.github/scripts/"
    mkdir -p "$REPO/InventoryApi/Test" "$REPO/InventorySales" "$REPO/_metapackage" "$REPO/docs"
    touch "$REPO/InventoryApi/registration.php" "$REPO/InventorySales/registration.php" "$REPO/InventoryApi/Test/FooTest.php" "$REPO/_metapackage/composer.json"
    cat > "$BATS_TEST_TMPDIR/bin/composer" <<'STUB'
#!/bin/sh
echo "composer $* in $(pwd)" >> "$CALLS"
STUB
    cat > "$BATS_TEST_TMPDIR/bin/git" <<'STUB'
#!/bin/sh
echo "git $*" >> "$CALLS"
if [ "$1" = clone ]; then
    eval "target=\${$#}"
    mkdir -p "$target/app/code/Magento/Catalog"
fi
STUB
    cat > "$BATS_TEST_TMPDIR/bin/php" <<'STUB'
#!/bin/sh
echo "php $*" >> "$CALLS"
case "$1" in
    */ci-newest-release.php)
        [ -n "${NO_RELEASE:-}" ] && exit 1
        echo 2.4.9-p1
        ;;
esac
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/composer" "$BATS_TEST_TMPDIR/bin/git" "$BATS_TEST_TMPDIR/bin/php"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    cd "$REPO"
}

@test "checks out the Magento source of the newest release that msi-core declares" {
    run "$TEST_SH" .github/scripts/ci-static-project.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    grep -qE "^php $REPO/.github/scripts/ci-newest-release.php [^ ]+ magento/product-community-edition https://mirror.mage-os.org/ $REPO\$" "$CALLS"
    grep -qF "git clone --quiet --depth 1 --branch 2.4.9-p1 https://github.com/magento/magento2 $BATS_TEST_TMPDIR/magento" "$CALLS"
}

@test "installs the dependencies of that checkout from the Mage-OS mirror" {
    run "$TEST_SH" .github/scripts/ci-static-project.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    grep -qF "composer config --no-interaction repositories.0 composer https://mirror.mage-os.org/ in $BATS_TEST_TMPDIR/magento" "$CALLS"
    [ "$(tail -n 1 "$CALLS")" = "composer install --no-interaction --no-progress in $BATS_TEST_TMPDIR/magento" ]
}

@test "refreshes the lock hash that the mirror made stale, before installing" {
    run "$TEST_SH" .github/scripts/ci-static-project.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    update=$(grep -n "^composer update --lock --no-interaction in $BATS_TEST_TMPDIR/magento\$" "$CALLS" | cut -d: -f1)
    install=$(grep -n "^composer install " "$CALLS" | cut -d: -f1)
    [ -n "$update" ]
    [ "$update" -lt "$install" ]
}

@test "places each module under app/code/Magento, where upstream's static tests expect it" {
    run "$TEST_SH" .github/scripts/ci-static-project.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    [ -f "$BATS_TEST_TMPDIR/magento/app/code/Magento/InventoryApi/registration.php" ]
    [ -f "$BATS_TEST_TMPDIR/magento/app/code/Magento/InventoryApi/Test/FooTest.php" ]
    [ -f "$BATS_TEST_TMPDIR/magento/app/code/Magento/InventorySales/registration.php" ]
}

@test "does not place directories that are not modules" {
    run "$TEST_SH" .github/scripts/ci-static-project.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    [ ! -e "$BATS_TEST_TMPDIR/magento/app/code/Magento/_metapackage" ]
    [ ! -e "$BATS_TEST_TMPDIR/magento/app/code/Magento/docs" ]
}

@test "keeps the modules Magento ships" {
    run "$TEST_SH" .github/scripts/ci-static-project.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    [ -d "$BATS_TEST_TMPDIR/magento/app/code/Magento/Catalog" ]
}

@test "checks out nothing when no release installs together with msi-core" {
    NO_RELEASE=1 run "$TEST_SH" .github/scripts/ci-static-project.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -ne 0 ]
    ! grep -q "git clone" "$CALLS"
    [ ! -e "$BATS_TEST_TMPDIR/magento" ]
}
