<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$out = $argv[1] ?? null;
if ($out === null) {
    fwrite(STDERR, "usage: upgrade-snapshot.php <output-file>\n");
    exit(2);
}
$om = Harness::bootstrap('adminhtml');
$connection = Harness::resource($om)->getConnection();
$skus = array_merge(
    [Harness::SKU_SPLIT, Harness::SKU_ABUNDANT, Harness::SKU_SMALL],
    array_map(static fn ($i) => 'product_dynamic_' . $i, range(1, 40))
);
$snapshot = ['salable' => [], 'index' => []];
$stockIds = array_keys($connection->fetchPairs('SELECT stock_id, name FROM inventory_stock ORDER BY stock_id'));
foreach ($stockIds as $stockId) {
    foreach ($skus as $sku) {
        $snapshot['salable'][$stockId . ':' . $sku] = Harness::salable($om, $sku, (int)$stockId);
        $snapshot['index'][$stockId . ':' . $sku] = Harness::stockIndexQty($om, $sku, (int)$stockId);
    }
}
$snapshot['source_items'] = $connection->fetchAll(
    'SELECT source_code, sku, quantity, status FROM inventory_source_item ORDER BY source_code, sku'
);
$snapshot['reservations'] = $connection->fetchAll(
    'SELECT stock_id, sku, source_code, object_increment_id, SUM(quantity) AS qty, COUNT(*) AS n
     FROM inventory_reservation GROUP BY stock_id, sku, source_code, object_increment_id ORDER BY 1, 2, 3, 4'
);
$snapshot['orders'] = $connection->fetchPairs('SELECT increment_id, state FROM sales_order ORDER BY entity_id');
file_put_contents($out, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
printf(
    "snapshot: %d salable probes, %d source items, %d reservation groups, %d orders\n",
    count($snapshot['salable']),
    count($snapshot['source_items']),
    count($snapshot['reservations']),
    count($snapshot['orders'])
);
