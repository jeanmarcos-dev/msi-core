<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$expectedWorkers = (int)($argv[1] ?? 2);
$om = Harness::bootstrap('adminhtml');
$lines = file(Harness::statePath('gaph-results.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
$results = array_values(array_filter(array_map(static fn ($line) => json_decode($line, true), $lines)));
$reached = count(array_filter($results, static fn ($row) => $row['reached']));
$sold = count(array_filter($results, static fn ($row) => $row['ok']));
$physical = (float)Harness::resource($om)->getConnection()->fetchOne(
    'SELECT SUM(quantity) FROM inventory_source_item WHERE sku = ? AND source_code LIKE ?',
    [Harness::SKU_SPLIT, 'slr%']
);

printf("reached=%d/%d sold=%d physical=%s\n", $reached, $expectedWorkers, $sold, $physical);
if ($reached < $expectedWorkers) {
    echo "ROUND NO-CONTENTION\n";
    exit(3);
}
if ($sold > $physical) {
    echo "ROUND OVERSOLD\n";
    exit(1);
}
echo "ROUND OK\n";
