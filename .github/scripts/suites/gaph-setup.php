<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$om = Harness::bootstrap('adminhtml');
Harness::cancelOpenOrders($om);
Harness::assignChannel($om, 'base', Harness::stockId($om, Harness::STOCK_NAME));
Harness::setSourceQty($om, Harness::SKU_SPLIT, ['slr_a' => 1.0, 'slr_b' => 0.0]);
Harness::reindexInventory($om);
@unlink(Harness::statePath('gaph-go'));
file_put_contents(Harness::statePath('gaph-results.txt'), '');
echo "setup: one shared unit on slr_a\n";
