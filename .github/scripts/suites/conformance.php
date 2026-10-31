<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';
require __DIR__ . '/Conformance.php';

use Suites\Conformance as Cnf;
use Suites\Harness;

$om = Harness::bootstrap('adminhtml');
$resource = Harness::resource($om);

echo "== setup ==\n";
$mirrorStockId = Cnf::ensureMirrorStock($om);
echo "mirror stock id: $mirrorStockId\n";

[$variantAttributeId, $variantOptions] = Cnf::ensureVariantAttribute($om);
echo "variant attribute: $variantAttributeId options=" . json_encode($variantOptions) . "\n";

Cnf::ensureProduct($om, Cnf::CNF_SIMPLE, 'simple');
Cnf::ensureProduct($om, Cnf::CNF_CHILD_A, 'simple', ['variant' => $variantOptions[0]]);
Cnf::ensureProduct($om, Cnf::CNF_CHILD_B, 'simple', ['variant' => $variantOptions[1]]);
Cnf::ensureProduct($om, Cnf::CNF_CONFIG, 'configurable');
Cnf::ensureProduct($om, Cnf::CNF_GROUPED, 'grouped');
Cnf::linkConfigurable($om, Cnf::CNF_CONFIG, [Cnf::CNF_CHILD_A, Cnf::CNF_CHILD_B], $variantAttributeId);
Cnf::linkGrouped($om, Cnf::CNF_GROUPED, [Cnf::CNF_CHILD_A]);

foreach ([Cnf::CNF_SIMPLE, Cnf::CNF_CHILD_A, Cnf::CNF_CHILD_B] as $sku) {
    Cnf::setMirroredQty($om, $sku, 100.0);
}

Cnf::setStockConfig($om, Cnf::CNF_CONFIG, ['is_in_stock' => 1]);
Cnf::setStockConfig($om, Cnf::CNF_GROUPED, ['is_in_stock' => 1]);
Cnf::reindex($om);

echo "\n== S12 structural: index table type ==\n";
Cnf::compare(
    'S12-structural',
    'inventory_stock_<id> type',
    Cnf::indexTableType($om, $resource, Cnf::CNF_DEFAULT_STOCK_ID),
    Cnf::indexTableType($om, $resource, $mirrorStockId)
);

echo "\n== S01 baseline: qty 100, in stock ==\n";
Cnf::compareStocks($om, 'S01-baseline', Cnf::CNF_SIMPLE, $mirrorStockId);

echo "\n== S02 qty 0 ==\n";
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 0.0);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S02-zero-qty', Cnf::CNF_SIMPLE, $mirrorStockId);

echo "\n== S03 source item OUT_OF_STOCK with qty 50 ==\n";
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 50.0, 0);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S03-oos-status', Cnf::CNF_SIMPLE, $mirrorStockId);

echo "\n== S04 min_qty 5 with qty 3 ==\n";
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_min_qty' => 0, 'min_qty' => 5, 'is_in_stock' => 1]);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 3.0, 1);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S04-min-qty', Cnf::CNF_SIMPLE, $mirrorStockId);

echo "\n== S05 backorders on, qty 0 ==\n";
Cnf::setStockConfig(
    $om,
    Cnf::CNF_SIMPLE,
    ['use_config_min_qty' => 1, 'use_config_backorders' => 0, 'backorders' => 1, 'is_in_stock' => 1]
);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 0.0, 1);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S05-backorders', Cnf::CNF_SIMPLE, $mirrorStockId);

echo "\n== S06 manage_stock off, qty 0 ==\n";
Cnf::setStockConfig(
    $om,
    Cnf::CNF_SIMPLE,
    ['use_config_backorders' => 1, 'use_config_manage_stock' => 0, 'manage_stock' => 0]
);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 0.0, 1);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S06-no-manage-stock', Cnf::CNF_SIMPLE, $mirrorStockId);

echo "\n== S07 product disabled ==\n";
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_manage_stock' => 1, 'manage_stock' => 1]);
Cnf::setProductStatus($om, Cnf::CNF_SIMPLE, false);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 100.0, 1);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S07-disabled', Cnf::CNF_SIMPLE, $mirrorStockId);
Cnf::setProductStatus($om, Cnf::CNF_SIMPLE, true);
Cnf::reindex($om);

echo "\n== S08 reservation of -5 against qty 100 ==\n";
Cnf::appendMirroredReservation($om, Cnf::CNF_SIMPLE, -5.0, $mirrorStockId, '{"cnf":"S08"}');
Cnf::compareStocks($om, 'S08-reservation', Cnf::CNF_SIMPLE, $mirrorStockId);
Cnf::appendMirroredReservation($om, Cnf::CNF_SIMPLE, 5.0, $mirrorStockId, '{"cnf":"S08-compensate"}');

echo "\n== S09 configurable with a salable child ==\n";
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_A, 100.0, 1);
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_B, 0.0, 1);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S09-configurable-on', Cnf::CNF_CONFIG, $mirrorStockId);

echo "\n== S10 configurable with every child out of stock ==\n";
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_A, 0.0, 1);
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_B, 0.0, 1);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S10-configurable-off', Cnf::CNF_CONFIG, $mirrorStockId);

echo "\n== S11 grouped product follows its associated child ==\n";
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_A, 100.0, 1);
Cnf::reindex($om);
Cnf::compareStocks($om, 'S11-grouped', Cnf::CNF_GROUPED, $mirrorStockId);

echo "\n== S13 sku absent from the catalog ==\n";
Cnf::compareStocks($om, 'S13-absent-sku', 'cnf_does_not_exist', $mirrorStockId);

echo "\n== S14 stock config write must not touch one source only ==\n";
Cnf::setStockConfig(
    $om,
    Cnf::CNF_SIMPLE,
    ['use_config_manage_stock' => 1, 'manage_stock' => 1, 'use_config_min_qty' => 1]
);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 3.0, 1);
Cnf::reindex($om);
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_min_qty' => 0, 'min_qty' => 5, 'is_in_stock' => 1]);
Cnf::reindex($om);
Cnf::compare(
    'S14-config-write',
    'source item status',
    Cnf::sourceItemStatus($om, Cnf::CNF_SIMPLE, Cnf::CNF_DEFAULT_SOURCE),
    Cnf::sourceItemStatus($om, Cnf::CNF_SIMPLE, Cnf::CNF_MIRROR_SOURCE)
);
Cnf::compareStocks($om, 'S14-config-write', Cnf::CNF_SIMPLE, $mirrorStockId);
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_min_qty' => 1, 'is_in_stock' => 1]);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 100.0, 1);
Cnf::reindex($om);

echo "\n== S15 product status change must reach every stock without a full reindex ==\n";
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_A, 100.0, 1);
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_B, 0.0, 1);
Cnf::reindex($om);
Cnf::assertSalableInBothStocks($om, 'S15-baseline', Cnf::CNF_CONFIG, $mirrorStockId, 1);
Cnf::setProductStatus($om, Cnf::CNF_CHILD_A, false);
Cnf::assertSalableInBothStocks($om, 'S15-status-disabled', Cnf::CNF_CONFIG, $mirrorStockId, 0);
Cnf::setProductStatus($om, Cnf::CNF_CHILD_A, true);
Cnf::assertSalableInBothStocks($om, 'S15-status-enabled', Cnf::CNF_CONFIG, $mirrorStockId, 1);
Cnf::reindex($om);

echo "\n== S16 mass attribute update must reach every stock without a full reindex ==\n";
Cnf::massSetProductStatus($om, [Cnf::CNF_CHILD_A], false);
Cnf::assertSalableInBothStocks($om, 'S16-mass-disabled', Cnf::CNF_CONFIG, $mirrorStockId, 0);
Cnf::massSetProductStatus($om, [Cnf::CNF_CHILD_A], true);
Cnf::assertSalableInBothStocks($om, 'S16-mass-enabled', Cnf::CNF_CONFIG, $mirrorStockId, 1);
Cnf::reindex($om);

echo "\n== S17 a config-only save must not rewrite quantities of a multi-source default stock ==\n";
$secondSourceLink = Cnf::attachSecondSourceToDefaultStock($om);
Cnf::setSourceQty($om, Cnf::CNF_SIMPLE, Cnf::CNF_DEFAULT_SOURCE, 40.0);
Cnf::setSourceQty($om, Cnf::CNF_SIMPLE, Cnf::CNF_SECOND_DEFAULT_SOURCE, 60.0);
Cnf::reindex($om);

$om->get(\Magento\CatalogInventory\Model\StockRegistryStorage::class)->clean();
Cnf::assert(
    'S17-multi-source',
    Cnf::CNF_SIMPLE . '::source quantities before',
    [Cnf::CNF_SECOND_DEFAULT_SOURCE => 60.0, Cnf::CNF_DEFAULT_SOURCE => 40.0],
    Cnf::sourceQuantities($om, $resource, Cnf::CNF_SIMPLE, [Cnf::CNF_DEFAULT_SOURCE, Cnf::CNF_SECOND_DEFAULT_SOURCE])
);
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_min_qty' => 0, 'min_qty' => 5, 'is_in_stock' => 1]);
Cnf::assert(
    'S17-multi-source',
    Cnf::CNF_SIMPLE . '::source quantities after',
    [Cnf::CNF_SECOND_DEFAULT_SOURCE => 60.0, Cnf::CNF_DEFAULT_SOURCE => 40.0],
    Cnf::sourceQuantities($om, $resource, Cnf::CNF_SIMPLE, [Cnf::CNF_DEFAULT_SOURCE, Cnf::CNF_SECOND_DEFAULT_SOURCE])
);
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_min_qty' => 1, 'is_in_stock' => 1]);
Cnf::detachSecondSourceFromDefaultStock($om, $secondSourceLink, Cnf::CNF_SIMPLE);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 100.0, 1);
Cnf::reindex($om);

echo "\n== S19 a quantity save must not leak into the next config-only save ==\n";
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 100.0, 1);
Cnf::reindex($om);

Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['qty' => 50, 'is_in_stock' => 1]);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 4.0, 1);
Cnf::reindex($om);
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_min_qty' => 0, 'min_qty' => 1, 'is_in_stock' => 1]);
Cnf::assert(
    'S19-stale-registry',
    Cnf::CNF_SIMPLE . '::source quantities',
    [Cnf::CNF_MIRROR_SOURCE => 4.0, Cnf::CNF_DEFAULT_SOURCE => 4.0],
    Cnf::sourceQuantities($om, $resource, Cnf::CNF_SIMPLE, [Cnf::CNF_DEFAULT_SOURCE, Cnf::CNF_MIRROR_SOURCE])
);
Cnf::setStockConfig($om, Cnf::CNF_SIMPLE, ['use_config_min_qty' => 1, 'is_in_stock' => 1]);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 100.0, 1);
Cnf::reindex($om);

echo "\n== S20 bundle selections must be filtered by the index, not by the legacy stock item ==\n";
Cnf::ensureBundle($om, Cnf::CNF_BUNDLE, [Cnf::CNF_CHILD_A, Cnf::CNF_CHILD_B]);
$bundleChildren = [Cnf::CNF_CHILD_A, Cnf::CNF_CHILD_B];

$bundleStockId = Cnf::currentWebsiteStockId($om);
echo "  (selections are filtered for stock $bundleStockId)\n";
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_A, 100.0, 1);
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_B, 100.0, 1);
Cnf::reindex($om);
Cnf::assert(
    'S20-bundle-both-salable',
    Cnf::CNF_BUNDLE . '::selections kept',
    Cnf::salableSkus($om, $bundleChildren, $bundleStockId),
    Cnf::bundleSelectionSkus($om, Cnf::CNF_BUNDLE)
);
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_B, 0.0, 0);
Cnf::reindex($om);
Cnf::assert(
    'S20-bundle-one-salable',
    Cnf::CNF_BUNDLE . '::selections kept',
    Cnf::salableSkus($om, $bundleChildren, $bundleStockId),
    Cnf::bundleSelectionSkus($om, Cnf::CNF_BUNDLE)
);
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_B, 0.0, 1);
Cnf::reindex($om);

echo "\n== S18 the admin product grid stock columns must come from the index of its scope ==\n";
$gridSkus = [Cnf::CNF_SIMPLE, Cnf::CNF_CHILD_A, Cnf::CNF_CHILD_B, Cnf::CNF_CONFIG, Cnf::CNF_GROUPED];
Cnf::setMirroredQty($om, Cnf::CNF_CHILD_B, 0.0, 1);
Cnf::setMirroredQty($om, Cnf::CNF_SIMPLE, 17.0, 1);
Cnf::reindex($om);

Cnf::assert(
    'S18-admin-grid',
    'qty and status columns',
    Cnf::indexColumns($om, $gridSkus, Cnf::CNF_DEFAULT_STOCK_ID),
    Cnf::gridStockColumns($om, $gridSkus)
);

exit(Cnf::verdict());
