<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Magento\Catalog\Model\Product;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\MessageQueue\ConsumerFactory;
use Magento\InventoryCache\Model\ResourceModel\GetProductSalabilityByStockIds;
use Magento\MysqlMq\Model\QueueManagement;
use Magento\PageCache\Model\Cache\Type as FullPageCache;
use Suites\Harness;

const TOPIC = 'inventory.reservations.updateSalabilityStatus';
const CANARY = 'msi_suite_canary_';
const BYSTANDER = CANARY . 'bystander';

$om = Harness::bootstrap('frontend');
$connection = Harness::resource($om)->getConnection();
$stockIds = array_map('intval', $connection->fetchCol('SELECT stock_id FROM inventory_stock'));
$slrStockId = Harness::stockId($om, Harness::STOCK_NAME);
$readSalability = $om->get(GetProductSalabilityByStockIds::class);
$caches = ['app' => $om->get(CacheInterface::class), 'full_page' => $om->get(FullPageCache::class)];

$pendingMessages = static function () use ($connection): int {
    return (int)$connection->fetchOne(
        'SELECT COUNT(*) FROM queue_message_status AS status'
        . ' JOIN queue ON queue.id = status.queue_id'
        . ' WHERE queue.name = ? AND status.status IN (?, ?)',
        [TOPIC, QueueManagement::MESSAGE_STATUS_NEW, QueueManagement::MESSAGE_STATUS_RETRY_REQUIRED]
    );
};
$changedProductIds = static function (array $before, array $after): array {
    $changed = [];
    foreach (array_keys($before + $after) as $stockId) {
        $old = $before[$stockId] ?? [];
        $new = $after[$stockId] ?? [];
        $changed[] = array_keys(array_diff_assoc($old, $new) + array_diff_assoc($new, $old));
    }
    $changed = array_values(array_unique(array_merge([], ...$changed)));
    sort($changed);

    return $changed;
};

$before = $readSalability->execute($stockIds);
$productIds = [];
foreach ($before as $salability) {
    $productIds += $salability;
}
$productIds = array_keys($productIds);
foreach ($caches as $cache) {
    $cache->save('1', BYSTANDER, ['msi_suite_canary']);
    foreach ($productIds as $productId) {
        $cache->save('1', CANARY . $productId, [Product::CACHE_TAG . '_' . $productId]);
    }
}

$smallQty = Harness::salable($om, Harness::SKU_SMALL, $slrStockId);
$smallId = (int)$connection->fetchOne(
    'SELECT entity_id FROM catalog_product_entity WHERE sku = ?',
    [Harness::SKU_SMALL]
);
if ($smallQty <= 0) {
    fwrite(STDERR, Harness::SKU_SMALL . " has nothing left to sell\n");
    exit(1);
}
Harness::placeOrder($om, Harness::SKU_SMALL, $smallQty);

$messages = $pendingMessages();
$start = microtime(true);
$om->get(ConsumerFactory::class)->get(TOPIC)->process($messages);
$drainMs = (microtime(true) - $start) * 1000;

$changed = $changedProductIds($before, $readSalability->execute($stockIds));
$cleaned = [];
$bystanderKept = [];
foreach ($caches as $name => $cache) {
    $ids = array_values(array_filter($productIds, static fn ($id) => $cache->load(CANARY . $id) === false));
    sort($ids);
    $cleaned[$name] = $ids;
    $bystanderKept[$name] = $cache->load(BYSTANDER) !== false;
    $cache->remove(BYSTANDER);
    foreach ($productIds as $productId) {
        $cache->remove(CANARY . $productId);
    }
}

$failures = [];
if (!in_array($smallId, $changed, true)) {
    $failures[] = Harness::SKU_SMALL . ' did not change salability, so the step proved nothing';
}
foreach ($cleaned as $name => $ids) {
    if ($ids !== $changed) {
        $failures[] = sprintf(
            '%s cache: %d cleaned without a salability change, %d changed without being cleaned',
            $name,
            count(array_diff($ids, $changed)),
            count(array_diff($changed, $ids))
        );
    }
    if (!$bystanderKept[$name]) {
        $failures[] = "$name cache: an entry with an unrelated tag was cleaned";
    }
}
if ($pendingMessages() !== 0) {
    $failures[] = 'the consumer left messages in the queue';
}

echo json_encode([
    'products_watched' => count($productIds),
    'messages_drained' => $messages,
    'drain_ms' => round($drainMs, 1),
    'salability_changed' => count($changed),
    'cleaned' => array_map('count', $cleaned),
    'failures' => $failures,
]) . "\n";
exit($failures ? 1 : 0);
