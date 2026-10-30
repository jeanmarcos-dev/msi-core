<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$om = Harness::bootstrap('frontend');
$connection = Harness::resource($om)->getConnection();

$tolerated = is_file(Harness::statePath('toggle-order.txt'))
    ? trim((string)file_get_contents(Harness::statePath('toggle-order.txt')))
    : '';
$failures = 0;

$unbalanced = $connection->fetchAll(
    'SELECT object_increment_id, source_code, SUM(quantity) AS balance FROM inventory_reservation
     WHERE object_increment_id IS NOT NULL AND object_increment_id <> ?
     GROUP BY object_increment_id, source_code HAVING ABS(SUM(quantity)) > 0.0001',
    [$tolerated]
);
$openOrders = count(Harness::openOrderIds($om));
if ($openOrders === 0 && $unbalanced) {
    $failures++;
    echo 'FAIL unbalanced (order, source) groups with no open orders: ' . json_encode($unbalanced) . "\n";
} else {
    printf("PASS per-(order,source) balance | unbalanced=%d open_orders=%d\n", count($unbalanced), $openOrders);
}

$withoutSource = (int)$connection->fetchOne(
    'SELECT COUNT(*) FROM inventory_reservation
     WHERE object_increment_id IS NOT NULL AND source_code IS NULL AND quantity < 0'
);
echo ($withoutSource === 0 ? 'PASS' : 'WARN') . " flag-on demand rows without source: $withoutSource\n";

$orphans = (int)$connection->fetchOne(
    'SELECT COUNT(*) FROM inventory_reservation r LEFT JOIN inventory_source s ON s.source_code = r.source_code
     WHERE r.source_code IS NOT NULL AND s.source_code IS NULL'
);
if ($orphans > 0) {
    $failures++;
}
echo ($orphans === 0 ? 'PASS' : 'FAIL') . " reservations referencing unknown sources: $orphans\n";

printf("\nLEDGER VALIDATION: %s\n", $failures ? 'FAIL' : 'PASS');
exit($failures ? 1 : 0);
