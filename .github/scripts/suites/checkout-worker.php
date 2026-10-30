<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

[, $workerId, $orders] = $argv + [null, '0', '5'];
$workerId = (int)$workerId;
$orders = (int)$orders;

$om = Harness::bootstrap('frontend');
$connection = Harness::resource($om)->getConnection();
$skus = $connection->fetchCol(
    'SELECT sku FROM inventory_source_item WHERE source_code = ? AND sku LIKE ?',
    ['slr_a', 'product_dynamic_%']
);
if (!$skus) {
    fwrite(STDERR, "no multi-source catalog skus\n");
    exit(1);
}

$times = [];
$errors = 0;
mt_srand($workerId * 7919);
for ($i = 0; $i < $orders; $i++) {
    $sku = $skus[mt_rand(0, count($skus) - 1)];
    try {
        $start = microtime(true);
        Harness::placeOrder($om, $sku, (float)mt_rand(1, 2));
        $times[] = (microtime(true) - $start) * 1000;
    } catch (\Throwable $e) {
        $errors++;
        fwrite(STDERR, "worker $workerId order $i ($sku): " . $e->getMessage() . "\n");
    }
}

sort($times);
echo json_encode([
    'worker' => $workerId,
    'orders_ok' => count($times),
    'errors' => $errors,
    'order_ms' => $times ? [
        'avg' => round(array_sum($times) / count($times), 1),
        'p50' => round($times[intdiv(count($times), 2)], 1),
        'p95' => round($times[(int)floor(count($times) * 0.95)] ?? end($times), 1),
        'max' => round(end($times), 1),
    ] : null,
]) . "\n";
exit($errors ? 1 : 0);
