#!/usr/bin/env bats

setup() {
    BIN="$BATS_TEST_TMPDIR/bin"
    OUT="$BATS_TEST_TMPDIR/diag"
    mkdir -p "$BIN"
    printf '#!/bin/sh\ncat "%s/mysql-reply" 2>/dev/null || echo mysql-reply\n' "$BATS_TEST_TMPDIR" > "$BIN/mysql"
    printf '#!/bin/sh\ncat "%s/curl-reply" 2>/dev/null || echo magento2_product_2_v1\n' "$BATS_TEST_TMPDIR" > "$BIN/curl"
    chmod +x "$BIN/mysql" "$BIN/curl"
    SCRIPT="$BATS_TEST_DIRNAME/../ci-mftf-diag.sh"
    PATH="$BIN:$PATH"
    export DB_HOST=127.0.0.1 DB_USER=root DB_PASSWORD=root DB_NAME=magento_mftf
    export OPENSEARCH_HOST=127.0.0.1 OPENSEARCH_PORT=9200
    export MFTF_DIAG_ONCE=1
}

@test "refuses to run without an output directory" {
    run sh "$SCRIPT"

    [ "$status" -ne 0 ]
    [[ "$output" == *usage* ]]
}

@test "writes every section the index diagnosis needs" {
    run sh "$SCRIPT" "$OUT"

    [ "$status" -eq 0 ]
    for section in stores store_groups inventory_stock_sales_channel indexer_state mview_state \
        'catalog_category_product_index_store and inventory_stock tables' 'price index by website' 'opensearch indices'; do
        grep -qF "## $section" "$OUT/diag.log"
    done
    grep -qF magento2_product_2_v1 "$OUT/diag.log"
}

@test "does not repeat a sample that did not change" {
    sh "$SCRIPT" "$OUT"
    sh "$SCRIPT" "$OUT"

    [ "$(grep -c '^=== ' "$OUT/diag.log")" -eq 1 ]
}

@test "appends a sample when the state changes" {
    sh "$SCRIPT" "$OUT"
    echo changed > "$BATS_TEST_TMPDIR/curl-reply"
    sh "$SCRIPT" "$OUT"

    [ "$(grep -c '^=== ' "$OUT/diag.log")" -eq 2 ]
    grep -qF changed "$OUT/diag.log"
}
