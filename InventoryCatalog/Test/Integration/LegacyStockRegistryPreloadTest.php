<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\CatalogInventory\Api\StockStatusCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockStatusRepositoryInterface;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryApi\Test\Fixture\SourceItem as SourceItemFixture;
use Magento\InventorySalesApi\Api\IsProductSalableInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

#[AppArea('frontend'), AppIsolation(true), DbIsolation(true)]
class LegacyStockRegistryPreloadTest extends TestCase
{
    private const SKU = 'registry-preload-simple';

    #[
        DataFixture(ProductFixture::class, ['sku' => self::SKU], 'product'),
        DataFixture(SourceItemFixture::class, ['sku' => self::SKU, 'source_code' => 'default', 'quantity' => 10]),
    ]
    public function testStock1ReadsComeFromItsIndexAfterTheLegacyRegistryWasPreloaded(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $productId = (int)DataFixtureStorageManager::getStorage()->get('product')->getId();
        $connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $connection->insertOnDuplicate(
            $connection->getTableName('cataloginventory_stock_status'),
            ['product_id' => $productId, 'website_id' => 0, 'stock_id' => 1, 'qty' => 0, 'stock_status' => 0],
            ['qty', 'stock_status']
        );

        $cachePool = $objectManager->get('Magento\InventorySalesApi\Model\CachePool');
        $cachePool->clean([self::SKU], 1);
        $criteria = $objectManager->get(StockStatusCriteriaInterfaceFactory::class)->create();
        $criteria->setProductsFilter([$productId]);
        $criteria->setScopeFilter(0);
        $registry = $objectManager->get(StockRegistryStorage::class);
        $legacyStatuses = $objectManager->get(StockStatusRepositoryInterface::class)->getList($criteria)->getItems();
        foreach ($legacyStatuses as $status) {
            $registry->setStockStatus((int)$status->getProductId(), 0, $status);
        }
        $cachePool->warmup([self::SKU], 1);

        $stockItemData = $objectManager->get(GetStockItemDataInterface::class)->execute(self::SKU, 1);
        self::assertSame(10.0, (float)$stockItemData[GetStockItemDataInterface::QUANTITY]);
        self::assertSame(1, (int)$stockItemData[GetStockItemDataInterface::IS_SALABLE]);
        self::assertTrue($objectManager->get(IsProductSalableInterface::class)->execute(self::SKU, 1));
    }
}
