<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

$count = (int)($argv[1] ?? 300);
$om = Suites\Harness::bootstrap('adminhtml');
$productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$productFactory = $om->get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);
for ($i = 1; $i <= $count; $i++) {
    $product = $productFactory->create();
    $product->setSku('product_dynamic_' . $i);
    $product->setName('Product dynamic ' . $i);
    $product->setTypeId('simple');
    $product->setAttributeSetId(4);
    $product->setPrice(10 + $i % 50);
    $product->setStatus(1);
    $product->setVisibility(4);
    $product->setWebsiteIds([1]);
    $product->setStockData(['manage_stock' => 1, 'use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);
    $productRepo->save($product);
}
echo "seeded $count products\n";
