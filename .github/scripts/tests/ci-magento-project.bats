#!/usr/bin/env bats

setup() {
    TEST_SH="$(command -v "${TEST_SH:-dash}")" || { echo "dash is required: install it or set TEST_SH" >&2; return 1; }
    REPO="$BATS_TEST_TMPDIR/repo"
    mkdir -p "$REPO/.github/scripts" "$BATS_TEST_TMPDIR/bin"
    cp "$BATS_TEST_DIRNAME/../ci-magento-project.sh" "$BATS_TEST_DIRNAME/../ci-unit.sh" "$REPO/.github/scripts/"
    cat > "$BATS_TEST_TMPDIR/bin/composer" <<'STUB'
#!/bin/sh
echo "composer $*" >> "$CALLS"
if [ "$1" = create-project ]; then
    eval "target=\${$#}"
    mkdir -p "$target"
fi
STUB
    cat > "$BATS_TEST_TMPDIR/bin/php" <<'STUB'
#!/bin/sh
echo "php $* in $(pwd)" >> "$CALLS"
case "$1" in
    */ci-newest-release.php)
        [ -n "${NO_RELEASE:-}" ] && exit 1
        [ -d "$2" ] || exit 3
        case "$3" in
            magento/product-community-edition) echo 2.4.9-p1 ;;
            mage-os/product-community-edition) echo 3.6.0 ;;
        esac
        ;;
esac
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/composer" "$BATS_TEST_TMPDIR/bin/php"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
    cd "$REPO"
}

@test "installs the newest Magento that msi-core declares from the Mage-OS mirror with a copy of msi-core from the checkout" {
    run "$TEST_SH" .github/scripts/ci-magento-project.sh magento "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    grep -qE "^php $REPO/.github/scripts/ci-newest-release.php [^ ]+ magento/product-community-edition https://mirror.mage-os.org/ $REPO in " "$CALLS"
    grep -qF "composer create-project --no-interaction --no-install --repository-url=https://mirror.mage-os.org/ magento/project-community-edition:2.4.9-p1 $BATS_TEST_TMPDIR/magento" "$CALLS"
    grep -qF "composer config --no-interaction repositories.0 composer https://mirror.mage-os.org/" "$CALLS"
    grep -qF "composer config --no-interaction repositories.msi-core {\"type\":\"path\",\"url\":\"$REPO\",\"options\":{\"symlink\":false}}" "$CALLS"
    grep -qF "composer require --no-interaction --no-update jeanmarcos/msi-core:*@dev" "$CALLS"
    [ "$(tail -n 1 "$CALLS")" = "composer install --no-interaction --no-progress" ]
}

@test "installs the newest Mage-OS that msi-core declares from the Mage-OS repository" {
    run "$TEST_SH" .github/scripts/ci-magento-project.sh mage-os "$BATS_TEST_TMPDIR/mageos"

    [ "$status" -eq 0 ]
    grep -qE "^php $REPO/.github/scripts/ci-newest-release.php [^ ]+ mage-os/product-community-edition https://repo.mage-os.org/ $REPO in " "$CALLS"
    grep -qF -- "--repository-url=https://repo.mage-os.org/ mage-os/project-community-edition:3.6.0 $BATS_TEST_TMPDIR/mageos" "$CALLS"
    grep -qF "composer config --no-interaction repositories.0 composer https://repo.mage-os.org/" "$CALLS"
}

@test "creates nothing when no release installs together with msi-core" {
    NO_RELEASE=1 run "$TEST_SH" .github/scripts/ci-magento-project.sh magento "$BATS_TEST_TMPDIR/magento"

    [ "$status" -ne 0 ]
    ! grep -q "create-project" "$CALLS"
    [ ! -e "$BATS_TEST_TMPDIR/magento" ]
}

@test "rejects an unknown platform before calling composer" {
    run "$TEST_SH" .github/scripts/ci-magento-project.sh magento-commerce "$BATS_TEST_TMPDIR/x"

    [ "$status" -eq 2 ]
    [[ "$output" == *"unknown platform: magento-commerce"* ]]
    [ ! -e "$CALLS" ]
}

@test "runs the unit suite of the installed msi-core from the Magento root with Magento's bootstrap" {
    mkdir -p "$BATS_TEST_TMPDIR/magento"

    run "$TEST_SH" .github/scripts/ci-unit.sh "$BATS_TEST_TMPDIR/magento"

    [ "$status" -eq 0 ]
    grep -qF "php vendor/bin/phpunit --configuration $BATS_TEST_TMPDIR/magento/vendor/jeanmarcos/msi-core/.github/phpunit-unit.xml --bootstrap dev/tests/unit/framework/bootstrap.php --cache-directory $BATS_TEST_TMPDIR/magento/var/phpunit-cache in $BATS_TEST_TMPDIR/magento" "$CALLS"
}

