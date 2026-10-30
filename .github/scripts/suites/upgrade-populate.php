<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Suites\Harness;

$om = Harness::bootstrap('adminhtml');
$orderManagement = $om->get(\Magento\Sales\Api\OrderManagementInterface::class);
Harness::reindexInventory($om);
$open = Harness::placeOrder($om, Harness::SKU_SPLIT, 7.0);
$shipped = Harness::placeOrder($om, Harness::SKU_SMALL, 2.0);
Harness::shipOrder($om, $shipped, 'slr_a');
$canceled = Harness::placeOrder($om, Harness::SKU_SPLIT, 2.0);
$orderManagement->cancel((int)$canceled->getEntityId());
printf(
    "open=%s shipped=%s canceled=%s\n",
    $open->getIncrementId(),
    $shipped->getIncrementId(),
    $canceled->getIncrementId()
);
