<?php
declare(strict_types=1);

require __DIR__ . '/Harness.php';

use Magento\Framework\Exception\NoSuchEntityException;
use Suites\Harness;

$om = Harness::bootstrap('adminhtml');
$resource = Harness::resource($om);
$multiSourceSkus = (int)($argv[1] ?? 300);

$sourceRepo = $om->get(\Magento\InventoryApi\Api\SourceRepositoryInterface::class);
$sourceFactory = $om->get(\Magento\InventoryApi\Api\Data\SourceInterfaceFactory::class);
foreach ([['slr_a', 'SLR Source A'], ['slr_b', 'SLR Source B']] as [$code, $name]) {
    try {
        $sourceRepo->get($code);
    } catch (NoSuchEntityException $e) {
        $source = $sourceFactory->create();
        $source->setSourceCode($code);
        $source->setName($name);
        $source->setEnabled(true);
        $source->setCountryId('US');
        $source->setPostcode('00000');
        $sourceRepo->save($source);
    }
}

$stockRepo = $om->get(\Magento\InventoryApi\Api\StockRepositoryInterface::class);
$stockFactory = $om->get(\Magento\InventoryApi\Api\Data\StockInterfaceFactory::class);
$stockIdByName = static function (string $name) use ($om, $stockRepo, $stockFactory): int {
    $existing = Harness::stockId($om, $name);
    if ($existing) {
        return $existing;
    }
    $stock = $stockFactory->create();
    $stock->setName($name);

    return (int)$stockRepo->save($stock);
};
$stockId = $stockIdByName(Harness::STOCK_NAME);
$siblingId = $stockIdByName(Harness::SIBLING_STOCK_NAME);

$linkFactory = $om->get(\Magento\InventoryApi\Api\Data\StockSourceLinkInterfaceFactory::class);
$links = [];
foreach ([[$stockId, 'slr_a', 1], [$stockId, 'slr_b', 2], [$siblingId, 'slr_a', 1]] as [$linkStock, $code, $priority]) {
    $link = $linkFactory->create();
    $link->setStockId($linkStock);
    $link->setSourceCode($code);
    $link->setPriority($priority);
    $links[] = $link;
}
$om->get(\Magento\InventoryApi\Api\StockSourceLinksSaveInterface::class)->execute($links);
Harness::assignChannel($om, 'base', $stockId);

$productRepo = $om->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$productFactory = $om->get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);
$quantities = [
    Harness::SKU_SPLIT => ['slr_a' => 5, 'slr_b' => 10],
    Harness::SKU_ABUNDANT => ['slr_a' => 100000, 'slr_b' => 100000],
    Harness::SKU_SMALL => ['slr_a' => 3, 'slr_b' => 2],
];
foreach ($quantities as $sku => $bySource) {
    try {
        $productRepo->get($sku);
    } catch (NoSuchEntityException $e) {
        $product = $productFactory->create();
        $product->setSku($sku);
        $product->setName($sku);
        $product->setTypeId('simple');
        $product->setAttributeSetId(4);
        $product->setPrice(10);
        $product->setStatus(1);
        $product->setVisibility(4);
        $product->setWebsiteIds([1]);
        $product->setStockData(['manage_stock' => 1, 'use_config_manage_stock' => 1]);
        $productRepo->save($product);
    }
    Harness::setSourceQty($om, $sku, $bySource);
}

$connection = $resource->getConnection();
$skus = $connection->fetchCol(
    $connection->select()
        ->from($resource->getTableName('catalog_product_entity'), ['sku'])
        ->where('type_id = ?', 'simple')
        ->where('sku LIKE ?', 'product_dynamic_%')
        ->order('entity_id')
        ->limit($multiSourceSkus)
);
foreach ($skus as $index => $sku) {
    Harness::setSourceQty($om, $sku, ['slr_a' => 50.0 + ($index % 100), 'slr_b' => 50.0 + ($index % 100)]);
}

$websiteId = 0;
$store = null;
if (($argv[2] ?? '') !== 'single') {
    $website = $om->create(\Magento\Store\Model\Website::class);
    $website->load(Harness::SECOND_WEBSITE_CODE, 'code');
    if (!$website->getId()) {
        $website->setCode(Harness::SECOND_WEBSITE_CODE)->setName('SLR Web2')->setDefaultGroupId(0)->save();
    }
    $websiteId = (int)$website->getId();
    $group = $om->create(\Magento\Store\Model\Group::class);
    foreach ($group->getCollection() as $candidate) {
        if ($candidate->getName() === 'SLR Web2 Group' && (int)$candidate->getWebsiteId() === $websiteId) {
            $group = $candidate;
            break;
        }
    }
    if (!$group->getId()) {
        $group->setWebsiteId($websiteId)->setName('SLR Web2 Group')->setCode('slrw2_group')
            ->setRootCategoryId(2)->setDefaultStoreId(0)->save();
    }
    $store = $om->create(\Magento\Store\Model\Store::class);
    $store->load('slrw2_view', 'code');
    if (!$store->getId()) {
        $store->setCode('slrw2_view')->setName('SLR Web2 View')->setWebsiteId($websiteId)
            ->setGroupId((int)$group->getId())->setIsActive(1)->setSortOrder(0)->save();
    }
    $website->setDefaultGroupId((int)$group->getId())->save();
    $group->setDefaultStoreId((int)$store->getId())->save();

    $configWriter = $om->get(\Magento\Framework\App\Config\Storage\WriterInterface::class);
    foreach (['web/unsecure/base_url', 'web/secure/base_url'] as $path) {
        $configWriter->save($path, 'http://slrw2.local/', 'websites', $websiteId);
    }
    Harness::assignChannel($om, Harness::SECOND_WEBSITE_CODE, $siblingId);

    $link = $om->get(\Magento\Catalog\Api\Data\ProductWebsiteLinkInterfaceFactory::class)->create();
    $link->setSku(Harness::SKU_SPLIT);
    $link->setWebsiteId($websiteId);
    $om->get(\Magento\Catalog\Api\ProductWebsiteLinkRepositoryInterface::class)->save($link);
    $om->get(\Magento\Store\Model\StoreManagerInterface::class)->reinitStores();
}
Harness::assignChannel($om, 'base', $stockId);

printf(
    "topology stock=%d sibling=%d website2=%d store2=%d multisource=%d\n",
    $stockId,
    $siblingId,
    $websiteId,
    $store ? (int)$store->getId() : 0,
    count($skus)
);
