#!/bin/sh
set -eu

usage='usage: ci-mftf-sampler.sh <output-dir>'
out=${1:?$usage}
interval=${MFTF_DIAG_INTERVAL:-10}
mkdir -p "$out"
log=$out/indexes.log
last=$out/.last
current=$out/.current

query() {
    MYSQL_PWD="${DB_PASSWORD:-root}" mysql --host="${DB_HOST:-127.0.0.1}" --user="${DB_USER:-root}" "${DB_NAME:-magento_mftf}" --batch "$@" 2>&1 || true
}

section() {
    printf '## %s\n' "$1"
}

sample() {
    section stores
    query --execute='SELECT store_id, code, website_id, group_id, is_active FROM store ORDER BY store_id'
    section store_groups
    query --execute='SELECT group_id, website_id, root_category_id, default_store_id FROM store_group ORDER BY group_id'
    section inventory_stock_sales_channel
    query --execute='SELECT * FROM inventory_stock_sales_channel ORDER BY stock_id, code'
    section indexer_state
    query --execute='SELECT indexer_id, status, updated FROM indexer_state ORDER BY indexer_id'
    section mview_state
    query --execute='SELECT view_id, mode, status, version_id FROM mview_state ORDER BY view_id'
    section 'catalog_category_product_index_store and inventory_stock tables'
    for table in $(query --skip-column-names --execute="SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name REGEXP '^(catalog_category_product_index_store|inventory_stock_)[0-9]+\$' ORDER BY table_name"); do
        case $table in
            catalog_category_product_index_store*)
                query --execute="SELECT '$table' AS tbl, COUNT(*) AS entries, COUNT(DISTINCT category_id) AS categories, COUNT(DISTINCT product_id) AS products FROM $table"
                ;;
            inventory_stock_1)
                query --execute="SELECT '$table' AS tbl, COUNT(*) AS entries, SUM(is_salable) AS salable FROM $table"
                ;;
            inventory_stock_*)
                query --execute="SELECT '$table' AS tbl, sku, quantity, is_salable FROM $table ORDER BY sku LIMIT 40"
                ;;
        esac
    done
    section 'price index by website'
    query --execute='SELECT website_id, customer_group_id, COUNT(*) AS products FROM catalog_product_index_price GROUP BY website_id, customer_group_id ORDER BY website_id, customer_group_id'
    section 'opensearch indices'
    curl --silent "http://${OPENSEARCH_HOST:-127.0.0.1}:${OPENSEARCH_PORT:-9200}/_cat/indices/magento2*?h=index,docs.count,health&s=index" || true
}

while :; do
    sample > "$current"
    if ! cmp -s "$current" "$last"; then
        { printf '=== %s\n' "$(date -u +%FT%TZ)"; cat "$current"; } >> "$log"
        cp "$current" "$last"
    fi
    [ -z "${MFTF_DIAG_ONCE:-}" ] || break
    sleep "$interval"
done
