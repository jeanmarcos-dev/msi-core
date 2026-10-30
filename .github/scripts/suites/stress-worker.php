<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Suites\Harness;

[, $workerId, $iterations, $sku, $qty] = $argv + [null, '0', '10', Harness::SKU_ABUNDANT, '3'];
$workerId = (int)$workerId;
$iterations = (int)$iterations;
$qty = (float)$qty;

$om = Harness::bootstrap('frontend');
$router = $om->get(\Magento\InventorySalesApi\Api\PlaceReservationsForSalesEventInterface::class);
$itemFactory = $om->get(\Magento\InventorySalesApi\Api\Data\ItemToSellInterfaceFactory::class);
$eventFactory = $om->get(\Magento\InventorySalesApi\Api\Data\SalesEventInterfaceFactory::class);
$extFactory = $om->get(\Magento\InventorySalesApi\Api\Data\SalesEventExtensionFactory::class);
$channel = $om->get(\Magento\InventorySalesApi\Api\Data\SalesChannelInterfaceFactory::class)->create(
    ['data' => ['type' => SalesChannelInterface::TYPE_WEBSITE, 'code' => 'base']]
);

$place = static function (
    string $incrementId,
    float $signedQty,
    string $eventType
) use (
    $router,
    $itemFactory,
    $eventFactory,
    $extFactory,
    $channel,
    $sku
): float {
    $event = $eventFactory->create([
        'type' => $eventType,
        'objectType' => SalesEventInterface::OBJECT_TYPE_ORDER,
        'objectId' => '0',
    ]);
    $event->setExtensionAttributes($extFactory->create(['data' => ['objectIncrementId' => $incrementId]]));
    $start = microtime(true);
    $router->execute([$itemFactory->create(['sku' => $sku, 'qty' => $signedQty])], $channel, $event);

    return (microtime(true) - $start) * 1000;
};

$placeTimes = [];
$compensateTimes = [];
$errors = 0;
for ($i = 1; $i <= $iterations; $i++) {
    $incrementId = sprintf('SLRS%03d%05d', $workerId, $i);
    try {
        $placeTimes[] = $place($incrementId, -$qty, SalesEventInterface::EVENT_ORDER_PLACED);
        $compensateTimes[] = $place($incrementId, $qty, SalesEventInterface::EVENT_SHIPMENT_CREATED);
    } catch (\Throwable $e) {
        $errors++;
        fwrite(STDERR, "worker $workerId iteration $i: " . $e->getMessage() . "\n");
    }
}

$stats = static function (array $times): array {
    if (!$times) {
        return ['avg' => 0, 'p95' => 0, 'max' => 0];
    }
    sort($times);

    return [
        'avg' => round(array_sum($times) / count($times), 2),
        'p95' => round($times[(int)floor(count($times) * 0.95)] ?? end($times), 2),
        'max' => round(end($times), 2),
    ];
};

echo json_encode([
    'worker' => $workerId,
    'iterations' => $iterations,
    'errors' => $errors,
    'place_ms' => $stats($placeTimes),
    'compensate_ms' => $stats($compensateTimes),
]) . "\n";
exit($errors ? 1 : 0);
