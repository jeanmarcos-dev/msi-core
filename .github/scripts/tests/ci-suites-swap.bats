#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    SOURCE="$BATS_TEST_TMPDIR/source"
    BIN="$BATS_TEST_TMPDIR/bin"
    CALLS="$BATS_TEST_TMPDIR/calls"
    mkdir -p "$MAGENTO/vendor/jeanmarcos/msi-core" "$MAGENTO/generated/code" "$MAGENTO/generated/metadata" "$MAGENTO/var/cache" "$SOURCE/.git" "$BIN"
    echo old > "$MAGENTO/vendor/jeanmarcos/msi-core/Old.php"
    echo new > "$SOURCE/New.php"
    echo ignored > "$SOURCE/.git/HEAD"
    printf '#!/bin/sh\necho "composer $*" >> "$CALLS"\n' > "$BIN/composer"
    chmod +x "$BIN/composer"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-suites-swap.sh"
    PATH="$BIN:$PATH"
    export CALLS
}

@test "replaces the installed msi-core with the given source" {
    run sh "$SCRIPT" "$MAGENTO" "$SOURCE"

    [ "$status" -eq 0 ]
    [ -f "$MAGENTO/vendor/jeanmarcos/msi-core/New.php" ]
    [ ! -e "$MAGENTO/vendor/jeanmarcos/msi-core/Old.php" ]
    [ ! -e "$MAGENTO/vendor/jeanmarcos/msi-core/.git" ]
}

@test "regenerates the autoloader and drops what Magento generated for the other version" {
    run sh "$SCRIPT" "$MAGENTO" "$SOURCE"

    grep -q "composer dump-autoload" "$CALLS"
    [ ! -e "$MAGENTO/generated/code" ]
    [ ! -e "$MAGENTO/generated/metadata" ]
    [ ! -e "$MAGENTO/var/cache" ]
}

@test "requires the Magento directory and the source" {
    run sh "$SCRIPT" "$MAGENTO"

    [ "$status" -ne 0 ]
}
