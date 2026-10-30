<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$om = Harness::bootstrap('adminhtml');
Harness::cancelOpenOrders($om);
Harness::setSourceQty($om, Harness::SKU_SPLIT, ['slr_a' => 5.0, 'slr_b' => 10.0]);
Harness::reindexInventory($om);
Harness::assignChannel($om, 'base', Harness::stockId($om, Harness::STOCK_NAME));
$om->get(\Magento\InventoryReservationsApi\Model\CleanupReservationsInterface::class)->execute();
@unlink(Harness::statePath('gaph-go'));
@unlink(Harness::statePath('gaph-results.txt'));
echo "teardown done\n";
