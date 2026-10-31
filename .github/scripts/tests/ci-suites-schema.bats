#!/usr/bin/env bats

setup() {
    BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$BIN"
    ALLOWLIST="$BATS_TEST_TMPDIR/allowlist"
    printf 'inventory_stock_([2-9]|[1-9][0-9]+)\n' > "$ALLOWLIST"
    cat > "$BIN/mysqldump" <<'STUB'
#!/bin/sh
for last; do :; done
cat "$DUMPS/$last.sql"
STUB
    chmod +x "$BIN/mysqldump"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-suites-schema.sh"
    PATH="$BIN:$PATH"
    export DUMPS="$BATS_TEST_TMPDIR/dumps"
    mkdir -p "$DUMPS"
}

table() {
    printf 'CREATE TABLE `%s` (\n  `sku` varchar(64) NOT NULL,\n  PRIMARY KEY (`sku`)\n) ENGINE=InnoDB AUTO_INCREMENT=%s DEFAULT CHARSET=utf8mb4;\n\n' "$1" "$2"
}

@test "passes when both databases hold the same tables" {
    { table inventory_stock_1 5; table inventory_source 7; } > "$DUMPS/a.sql"
    { table inventory_stock_1 99; table inventory_source 1; } > "$DUMPS/b.sql"

    run sh "$SCRIPT" a b "$ALLOWLIST"

    [ "$status" -eq 0 ]
}

@test "ignores the stock tables the fixtures create" {
    { table inventory_stock_1 1; table inventory_stock_2 1; table inventory_stock_10 1; } > "$DUMPS/a.sql"
    { table inventory_stock_1 1; } > "$DUMPS/b.sql"

    run sh "$SCRIPT" a b "$ALLOWLIST"

    [ "$status" -eq 0 ]
}

preamble() {
    printf '/*!40101 SET @saved_cs_client     = @@character_set_client */;\n/*!50503 SET character_set_client = utf8mb4 */;\n'
    table "$1" "$2"
    printf '/*!40101 SET character_set_client = @saved_cs_client */;\n'
}

@test "drops the session boilerplate mysqldump writes around an excluded table" {
    { preamble inventory_stock_1 1; preamble inventory_stock_2 1; preamble inventory_source 1; } > "$DUMPS/a.sql"
    { preamble inventory_stock_1 1; preamble inventory_source 1; } > "$DUMPS/b.sql"

    run sh "$SCRIPT" a b "$ALLOWLIST"

    [ "$status" -eq 0 ]
}

@test "fails and names a table that only one database has" {
    { table inventory_stock_1 1; table inventory_reservation_order_balance 1; } > "$DUMPS/a.sql"
    { table inventory_stock_1 1; } > "$DUMPS/b.sql"

    run sh "$SCRIPT" a b "$ALLOWLIST"

    [ "$status" -ne 0 ]
    [[ "$output" == *"inventory_reservation_order_balance"* ]]
}

@test "fails on a trigger whose body differs" {
    { table inventory_stock_1 1; printf 'CREATE TRIGGER `t` AFTER INSERT ON `x` FOR EACH ROW SELECT NOW()\n'; } > "$DUMPS/a.sql"
    { table inventory_stock_1 1; printf 'CREATE TRIGGER `t` AFTER INSERT ON `x` FOR EACH ROW SELECT CURRENT_TIMESTAMP\n'; } > "$DUMPS/b.sql"

    run sh "$SCRIPT" a b "$ALLOWLIST"

    [ "$status" -ne 0 ]
    [[ "$output" == *"CURRENT_TIMESTAMP"* ]]
}

@test "requires both databases and the allow-list" {
    run sh "$SCRIPT" a b

    [ "$status" -ne 0 ]
}
