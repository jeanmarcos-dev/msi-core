#!/usr/bin/env bats

setup() {
    MAGENTO="$BATS_TEST_TMPDIR/magento"
    PREVIOUS="$BATS_TEST_TMPDIR/previous"
    CURRENT="$BATS_TEST_TMPDIR/current"
    BIN="$BATS_TEST_TMPDIR/bin"
    WORK="$BATS_TEST_TMPDIR/work"
    CALLS="$BATS_TEST_TMPDIR/calls"
    DUMPS="$BATS_TEST_TMPDIR/dumps"
    mkdir -p "$MAGENTO/vendor/jeanmarcos/msi-core" "$MAGENTO/bin" "$PREVIOUS" "$CURRENT" "$BIN" "$WORK" "$DUMPS"
    echo previous > "$PREVIOUS/Marker.php"
    echo current > "$CURRENT/Marker.php"
    printf 'inventory_stock_([2-9]|[1-9][0-9]+)\n' > "$BATS_TEST_TMPDIR/allowlist"
    printf 'CREATE TABLE `t` (\n  `a` int\n) ENGINE=InnoDB;\n' > "$DUMPS/magento_upgraded.sql"
    cp "$DUMPS/magento_upgraded.sql" "$DUMPS/magento_fresh.sql"
    cat > "$BIN/php" <<'STUB'
#!/bin/sh
echo "marker=$(cat "$MAGENTO_ROOT/vendor/jeanmarcos/msi-core/Marker.php") $*" >> "$CALLS"
case "$*" in
    *upgrade-snapshot.php*)
        for out; do :; done
        count=$(cat "$SNAPSHOT_COUNT" 2>/dev/null || echo 0)
        count=$((count + 1))
        echo "$count" > "$SNAPSHOT_COUNT"
        if [ -n "${SNAPSHOT_CHANGES_AT:-}" ] && [ "$count" -eq "$SNAPSHOT_CHANGES_AT" ]; then
            echo changed > "$out"
        else
            echo same > "$out"
        fi
        ;;
    *ledger.php*) exit "${LEDGER_STATUS:-0}" ;;
esac
STUB
    printf '#!/bin/sh\nexit 0\n' > "$BIN/composer"
    printf '#!/bin/sh\nfor last; do :; done\ncat "$DUMPS/$last.sql"\n' > "$BIN/mysqldump"
    printf '#!/bin/sh\nexit 0\n' > "$BIN/mysql"
    printf '#!/bin/sh\nexit 0\n' > "$BIN/sleep"
    chmod +x "$BIN"/*
    SCRIPT="$BATS_TEST_DIRNAME/../ci-suites-upgrade.sh"
    PATH="$BIN:$PATH"
    export CALLS DUMPS SNAPSHOT_COUNT="$BATS_TEST_TMPDIR/snapshot-count" DB_NAME=magento_upgraded FRESH_DB_NAME=magento_fresh
    export ALLOWLIST="$BATS_TEST_TMPDIR/allowlist"
}

@test "installs the previous release, populates it and upgrades to the current one" {
    run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT" "$WORK"

    [ "$status" -eq 0 ]
    grep -q "marker=previous .*setup:install" "$CALLS"
    grep -q "marker=previous .*upgrade-populate.php" "$CALLS"
    grep -q "marker=current .*setup:upgrade" "$CALLS"
    [ "$(grep -c 'setup:install' "$CALLS")" -eq 2 ]
}

@test "compares the schema of the upgraded database with a fresh install of the current release" {
    run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT" "$WORK"

    [ "$status" -eq 0 ]
    [[ "$output" == *"schemas are identical"* ]]
}

@test "fails when the schemas differ" {
    printf 'CREATE TABLE `extra` (\n  `a` int\n) ENGINE=InnoDB;\n' >> "$DUMPS/magento_fresh.sql"

    run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT" "$WORK"

    [ "$status" -ne 0 ]
    [[ "$output" == *"extra"* ]]
}

@test "fails when the upgrade changes stock, reservations or salable quantities" {
    SNAPSHOT_CHANGES_AT=2 run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT" "$WORK"

    [ "$status" -ne 0 ]
    ! grep -q "ledger.php" "$CALLS"
}

@test "fails when the reindex after the upgrade changes them" {
    SNAPSHOT_CHANGES_AT=3 run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT" "$WORK"

    [ "$status" -ne 0 ]
}

@test "fails when releasing the orders placed before the upgrade unbalances the ledger" {
    LEDGER_STATUS=1 run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT" "$WORK"

    [ "$status" -ne 0 ]
}

@test "reindexes before taking the baseline snapshot" {
    run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT" "$WORK"

    first=$(grep -n "indexer:reindex" "$CALLS" | head -n 1 | cut -d: -f1)
    snapshot=$(grep -n "upgrade-snapshot.php" "$CALLS" | head -n 1 | cut -d: -f1)
    [ "$first" -lt "$snapshot" ]
}

@test "requires the four directories" {
    run sh "$SCRIPT" "$MAGENTO" "$PREVIOUS" "$CURRENT"

    [ "$status" -ne 0 ]
}
