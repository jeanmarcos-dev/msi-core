<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration\Indexer;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Model\GetStockItemData\CacheStorage;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemDataCache;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemsDataCache;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class ReindexRefreshesStockItemDataCacheTest extends TestCase
{
    private const STOCK_ID = 10;
    private const SKU = 'SKU-1';

    /**
     * @var RemoveIndexData
     */
    private $removeIndexData;

    protected function setUp(): void
    {
        $this->removeIndexData = Bootstrap::getObjectManager()->get(RemoveIndexData::class);
        $this->removeIndexData->execute([10, 20, 30]);
        Bootstrap::getObjectManager()->get(CacheStorage::class)->_resetState();
    }

    protected function tearDown(): void
    {
        Bootstrap::getObjectManager()->get(CacheStorage::class)->_resetState();
        $this->removeIndexData->execute([10, 20, 30]);
    }

    /**
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDbIsolation disabled
     */
    public function testTheBulkStockItemDataReadAfterAReindexIsTheReindexedOne(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);
        $cache = $objectManager->get(GetStockItemsDataCache::class);
        self::assertSame(1, (int)$cache->execute([self::SKU], self::STOCK_ID)[self::SKU]['is_salable']);

        $this->markEverySourceItemOutOfStock();
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);

        self::assertSame(0, (int)$cache->execute([self::SKU], self::STOCK_ID)[self::SKU]['is_salable']);
    }

    /**
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/sources.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stocks.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/source_items.php
     * @magentoDataFixture Magento_InventoryApi::Test/_files/stock_source_links.php
     * @magentoDbIsolation disabled
     */
    public function testTheSingleStockItemDataReadAfterAReindexIsTheReindexedOne(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);
        $cache = $objectManager->get(GetStockItemDataCache::class);
        self::assertSame(1, (int)$cache->execute(self::SKU, self::STOCK_ID)['is_salable']);

        $this->markEverySourceItemOutOfStock();
        $objectManager->get(StockIndexer::class)->executeList([self::STOCK_ID]);

        self::assertSame(0, (int)$cache->execute(self::SKU, self::STOCK_ID)['is_salable']);
    }

    private function markEverySourceItemOutOfStock(): void
    {
        $resourceConnection = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $resourceConnection->getConnection()->update(
            $resourceConnection->getTableName('inventory_source_item'),
            ['status' => 0],
            ['sku = ?' => self::SKU]
        );
    }
}
