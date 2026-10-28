#!/usr/bin/env bats

setup() {
    bats_require_minimum_version 1.5.0
    SCRIPT="$BATS_TEST_DIRNAME/../ci-newest-release.php"
    PROBE="$BATS_TEST_TMPDIR/probe"
    mkdir -p "$PROBE" "$BATS_TEST_TMPDIR/bin"
    cat > "$BATS_TEST_TMPDIR/bin/composer" <<'STUB'
#!/bin/sh
echo "composer $*" >> "$CALLS"
echo "Resolving dependencies"
[ -n "${COMPOSER_FAILS:-}" ] && exit 1
for argument in "$@"; do
    case "$argument" in
        --working-dir=*) dir=${argument#--working-dir=} ;;
    esac
done
cat > "$dir/composer.lock" <<'LOCK'
{"packages": [
    {"name": "jeanmarcos/msi-core", "version": "dev-main"},
    {"name": "magento/framework", "version": "103.0.9-p1"},
    {"name": "magento/product-community-edition", "version": "2.4.9-p1"}
]}
LOCK
STUB
    chmod +x "$BATS_TEST_TMPDIR/bin/composer"
    export PATH="$BATS_TEST_TMPDIR/bin:$PATH"
    export CALLS="$BATS_TEST_TMPDIR/calls"
}

@test "prints the newest product release that installs together with the msi-core checkout" {
    run --separate-stderr php "$SCRIPT" "$PROBE" magento/product-community-edition https://mirror.mage-os.org/ /checkout

    [ "$status" -eq 0 ]
    [ "$output" = "2.4.9-p1" ]
    grep -qF "composer update --working-dir=$PROBE --no-install --no-plugins --no-scripts --no-interaction --no-progress" "$CALLS"
}

@test "lets composer resolve the product against the msi-core constraints and nothing else" {
    run php "$SCRIPT" "$PROBE" mage-os/product-community-edition https://repo.mage-os.org/ /checkout

    [ "$(php -r 'echo json_encode(json_decode(file_get_contents($argv[1]), true));' "$PROBE/composer.json")" = \
        '{"repositories":[{"type":"composer","url":"https:\/\/repo.mage-os.org\/"},{"type":"path","url":"\/checkout"}],"require":{"mage-os\/product-community-edition":"*","jeanmarcos\/msi-core":"*@dev"}}' ]
}

@test "fails when no product release installs together with the msi-core checkout" {
    COMPOSER_FAILS=1 run --separate-stderr php "$SCRIPT" "$PROBE" magento/product-community-edition https://mirror.mage-os.org/ /checkout

    [ "$status" -ne 0 ]
    [ "$output" = "" ]
}

@test "fails when the resolved lock does not contain the product" {
    run --separate-stderr php "$SCRIPT" "$PROBE" mage-os/product-community-edition https://repo.mage-os.org/ /checkout

    [ "$status" -eq 1 ]
    [ "$output" = "" ]
    [[ "$stderr" == *"mage-os/product-community-edition is not in the resolved lock"* ]]
}
