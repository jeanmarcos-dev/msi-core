<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$om = Harness::bootstrap('frontend');
$resource = Harness::resource($om);
$getSalableQty = $om->get(\Magento\InventorySalesApi\Api\GetProductSalableQtyInterface::class);
$orderManagement = $om->get(\Magento\Sales\Api\OrderManagementInterface::class);
$connection = $resource->getConnection();
$slrStock = Harness::stockId($om, Harness::STOCK_NAME);
$slrSibling = Harness::stockId($om, Harness::SIBLING_STOCK_NAME);
$failures = 0;

$ledger = static function (string $incrementId) use ($connection): array {
    $rows = [];
    foreach ($connection->fetchAll(
        'SELECT source_code, quantity FROM inventory_reservation WHERE object_increment_id = ? ORDER BY reservation_id',
        [$incrementId]
    ) as $row) {
        $rows[] = ($row['source_code'] ?? 'NULL') . ':' . (float)$row['quantity'];
    }

    return $rows;
};
$legacyLedger = static fn (string $incrementId): array => $connection->fetchAll(
    'SELECT source_code, object_increment_id, quantity FROM inventory_reservation WHERE metadata LIKE ?',
    ['%' . $incrementId . '%']
);
$sourceQty = static fn (string $sku): array => array_map('floatval', $connection->fetchPairs(
    "SELECT source_code, quantity FROM inventory_source_item
     WHERE sku = ? AND source_code LIKE 'slr%' ORDER BY source_code",
    [$sku]
));
$check = static function (string $label, $expected, $actual) use (&$failures): void {
    $ok = $expected == $actual;
    if (!$ok) {
        $failures++;
    }
    printf(
        "%s %s | expected=%s actual=%s\n",
        $ok ? 'PASS' : 'FAIL',
        $label,
        json_encode($expected),
        json_encode($actual)
    );
};
$place = static fn (string $sku, float $qty) => Harness::placeOrder($om, $sku, $qty);
$ship = static fn ($order, ?string $source, array $itemQtys = []) =>
    Harness::shipOrder($om, $order, $source, $itemQtys);
$toggleFlag = static function (string $value) use ($om): void {
    Harness::setConfig($om, 'cataloginventory/source_reservations/enabled', $value);
};


echo "== T1: place order qty 7 (SLR-1: slr_a=5 pri1, slr_b=10 pri2) ==\n";
$order1 = $place('SLR-1', 7);
$inc1 = $order1->getIncrementId();
$check('T1 split', ['slr_a:-5', 'slr_b:-2'], $ledger($inc1));
$check('T1 salable stock5', 8.0, $getSalableQty->execute('SLR-1', $slrStock));
$check('T1 salable stock6 (cross-stock)', 0.0, $getSalableQty->execute('SLR-1', $slrSibling));

echo "== T2: ship ALL 7 from slr_b (reserved mostly on slr_a) ==\n";
$ship($order1, 'slr_b');
$check('T2 compensation to original sources', ['slr_a:-5', 'slr_b:-2', 'slr_a:5', 'slr_b:2'], $ledger($inc1));
$check('T2 physical qty', ['slr_a' => 5.0, 'slr_b' => 3.0], $sourceQty('SLR-1'));
$check('T2 salable stock5', 8.0, $getSalableQty->execute('SLR-1', $slrStock));
$check('T2 salable stock6 recovered', 5.0, $getSalableQty->execute('SLR-1', $slrSibling));

echo "== T3: place order qty 6 then cancel ==\n";
$order2 = $place('SLR-1', 6);
$inc2 = $order2->getIncrementId();
$check('T3 split', ['slr_a:-5', 'slr_b:-1'], $ledger($inc2));
$check('T3 salable stock6', 0.0, $getSalableQty->execute('SLR-1', $slrSibling));
$orderManagement->cancel((int)$order2->getEntityId());
$check('T3 cancel compensation', ['slr_a:-5', 'slr_b:-1', 'slr_a:5', 'slr_b:1'], $ledger($inc2));
$check('T3 physical untouched', ['slr_a' => 5.0, 'slr_b' => 3.0], $sourceQty('SLR-1'));
$check('T3 salable stock6', 5.0, $getSalableQty->execute('SLR-1', $slrSibling));

echo "== T4: place qty 4, partial ship 2 from slr_a, cancel remainder ==\n";
$order3 = $place('SLR-1', 4);
$inc3 = $order3->getIncrementId();
$check('T4 split', ['slr_a:-4'], $ledger($inc3));
$ship($order3, 'slr_a', ['SLR-1' => 2]);
$check('T4 partial ship compensation', ['slr_a:-4', 'slr_a:2'], $ledger($inc3));
$check('T4 physical after partial', ['slr_a' => 3.0, 'slr_b' => 3.0], $sourceQty('SLR-1'));
$orderManagement->cancel((int)$order3->getEntityId());
$check('T4 cancel remainder', ['slr_a:-4', 'slr_a:2', 'slr_a:2'], $ledger($inc3));

echo "== T5: credit memo (invoiced, unshipped, return to stock) ==\n";
$order4 = $place('SLR-1', 3);
$inc4 = $order4->getIncrementId();
$check('T5 split', ['slr_a:-3'], $ledger($inc4));
$om->get(\Magento\Sales\Api\InvoiceOrderInterface::class)->execute((int)$order4->getEntityId());
$creditmemoArgsFactory = $om->get(\Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterfaceFactory::class);
$creditmemoArgsExtFactory = $om->get(
    \Magento\Sales\Api\Data\CreditmemoCreationArgumentsExtensionInterfaceFactory::class
);
$orderItemIds = [];
foreach ($order4->getAllItems() as $item) {
    $orderItemIds[] = (int)$item->getItemId();
}
$creditmemoArgs = $creditmemoArgsFactory->create();
$creditmemoArgs->setExtensionAttributes(
    $creditmemoArgsExtFactory->create()->setReturnToStockItems($orderItemIds)
);
$om->get(\Magento\Sales\Api\RefundOrderInterface::class)
    ->execute((int)$order4->getEntityId(), [], false, false, null, $creditmemoArgs);
$check('T5 creditmemo compensation', ['slr_a:-3', 'slr_a:3'], $ledger($inc4));
$check('T5 physical untouched', ['slr_a' => 3.0, 'slr_b' => 3.0], $sourceQty('SLR-1'));

echo "== T6: flag OFF regression ==\n";
$toggleFlag('0');
$order5 = $place('SLR-1', 2);
$inc5 = $order5->getIncrementId();
$check('T6 flag off single stock row', [], $ledger($inc5));
$legacyRows = $legacyLedger($inc5);
$check('T6 legacy row count', 1, count($legacyRows));
$check(
    'T6 legacy row shape',
    ['source_code' => null, 'object_increment_id' => null, 'quantity' => '-2.0000'],
    $legacyRows
        ? array_intersect_key($legacyRows[0], array_flip(['source_code', 'object_increment_id', 'quantity']))
        : null
);
$orderManagement->cancel((int)$order5->getEntityId());
$toggleFlag('1');

echo "== T7: toggle mid-flight (placed OFF, compensated ON) ==\n";
$toggleFlag('0');
$order6 = $place('SLR-1', 2);
$inc6 = $order6->getIncrementId();
file_put_contents(Harness::statePath('toggle-order.txt'), $inc6);
$toggleFlag('1');
$orderManagement->cancel((int)$order6->getEntityId());
$toggleRows = $legacyLedger($inc6);
$toggleSum = array_sum(array_map(fn ($r) => (float)$r['quantity'], $toggleRows));
$toggleSources = array_unique(array_map(fn ($r) => $r['source_code'], $toggleRows));
$check('T7 balanced at stock level', 0.0, $toggleSum);
$check('T7 compensation stayed stock-scoped', [null], array_values($toggleSources));

printf("\n%s — failures: %d\n", $failures ? 'E2E RESULT: FAIL' : 'E2E RESULT: PASS', $failures);
exit($failures ? 1 : 0);
