<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class SourceItemsSaveExistingItemsTest extends TestCase
{
    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemsSave;

    /**
     * @var SourceItemInterfaceFactory
     */
    private $sourceItemFactory;

    /**
     * @var GetStockItemDataInterface
     */
    private $getStockItemData;

    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @var string
     */
    private $defaultSourceCode;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->sourceItemsSave = $objectManager->get(SourceItemsSaveInterface::class);
        $this->sourceItemFactory = $objectManager->get(SourceItemInterfaceFactory::class);
        $this->getStockItemData = $objectManager->get(GetStockItemDataInterface::class);
        $this->connection = $objectManager->get(ResourceConnection::class)->getConnection();
        $this->defaultSourceCode = $objectManager->get(DefaultSourceProviderInterface::class)->getCode();
    }

    /**
     * @return void
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testChangedExistingItemsKeepTheirIdAndReachTheIndex(): void
    {
        $idsBefore = $this->getSourceItemIds();

        $this->sourceItemsSave->execute([
            $this->createSourceItem('SKU-1', 9, SourceItemInterface::STATUS_IN_STOCK),
            $this->createSourceItem('SKU-2', 5, SourceItemInterface::STATUS_OUT_OF_STOCK),
            $this->createSourceItem('SKU-3', 4, SourceItemInterface::STATUS_IN_STOCK),
        ]);

        $rows = $this->getSourceItemRows();
        self::assertSame($idsBefore['SKU-1'], (int)$rows['SKU-1']['source_item_id']);
        self::assertSame($idsBefore['SKU-2'], (int)$rows['SKU-2']['source_item_id']);
        self::assertSame($idsBefore['SKU-3'], (int)$rows['SKU-3']['source_item_id']);
        self::assertEquals(9, $rows['SKU-1']['quantity']);
        self::assertEquals(SourceItemInterface::STATUS_OUT_OF_STOCK, $rows['SKU-2']['status']);
        self::assertEquals(4, $rows['SKU-3']['quantity']);
        self::assertEquals(SourceItemInterface::STATUS_IN_STOCK, $rows['SKU-3']['status']);

        $sku1 = $this->getStockItemData->execute('SKU-1', Stock::DEFAULT_STOCK_ID);
        self::assertEquals(9, $sku1[GetStockItemDataInterface::QUANTITY]);
        self::assertEquals(1, $sku1[GetStockItemDataInterface::IS_SALABLE]);
        $sku2 = $this->getStockItemData->execute('SKU-2', Stock::DEFAULT_STOCK_ID);
        self::assertEquals(0, $sku2[GetStockItemDataInterface::IS_SALABLE]);
        $sku3 = $this->getStockItemData->execute('SKU-3', Stock::DEFAULT_STOCK_ID);
        self::assertEquals(1, $sku3[GetStockItemDataInterface::IS_SALABLE]);
        self::assertEquals(4, $sku3[GetStockItemDataInterface::QUANTITY]);
    }

    /**
     * @return void
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testNewAndExistingItemsInOneSave(): void
    {
        $idsBefore = $this->getSourceItemIds();
        self::assertArrayNotHasKey('SKU-5', $idsBefore);

        $this->sourceItemsSave->execute([
            $this->createSourceItem('SKU-5', 11, SourceItemInterface::STATUS_IN_STOCK),
            $this->createSourceItem('SKU-1', 7, SourceItemInterface::STATUS_IN_STOCK),
            $this->createSourceItem('SKU-4', 2, SourceItemInterface::STATUS_IN_STOCK),
        ]);

        $rows = $this->getSourceItemRows();
        self::assertSame($idsBefore['SKU-1'], (int)$rows['SKU-1']['source_item_id']);
        self::assertSame($idsBefore['SKU-4'], (int)$rows['SKU-4']['source_item_id']);
        self::assertEquals(7, $rows['SKU-1']['quantity']);
        self::assertEquals(2, $rows['SKU-4']['quantity']);
        self::assertEquals(11, $rows['SKU-5']['quantity']);
        self::assertGreaterThan(max($idsBefore), (int)$rows['SKU-5']['source_item_id']);

        $sku5 = $this->getStockItemData->execute('SKU-5', Stock::DEFAULT_STOCK_ID);
        self::assertEquals(11, $sku5[GetStockItemDataInterface::QUANTITY]);
        self::assertEquals(1, $sku5[GetStockItemDataInterface::IS_SALABLE]);
        $sku4 = $this->getStockItemData->execute('SKU-4', Stock::DEFAULT_STOCK_ID);
        self::assertEquals(1, $sku4[GetStockItemDataInterface::IS_SALABLE]);
        self::assertEquals(2, $sku4[GetStockItemDataInterface::QUANTITY]);
    }

    /**
     * @return void
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testUnchangedExistingItemsAreLeftAsTheyWere(): void
    {
        $rowsBefore = $this->getSourceItemRows();
        $autoIncrementBefore = $this->connection->showTableStatus('inventory_source_item')['Auto_increment'];

        $this->sourceItemsSave->execute([
            $this->createSourceItem('SKU-1', 5.5, SourceItemInterface::STATUS_IN_STOCK),
            $this->createSourceItem('SKU-2', 5, SourceItemInterface::STATUS_IN_STOCK),
        ]);

        self::assertEquals($rowsBefore, $this->getSourceItemRows());
        self::assertSame(
            $autoIncrementBefore,
            $this->connection->showTableStatus('inventory_source_item')['Auto_increment']
        );
        $sku1 = $this->getStockItemData->execute('SKU-1', Stock::DEFAULT_STOCK_ID);
        self::assertEquals(5.5, $sku1[GetStockItemDataInterface::QUANTITY]);
    }

    /**
     * @return void
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testTheFrozenLegacyStockTablesAreNotWritten(): void
    {
        $writesBefore = $this->getLegacyWriteCount();

        $this->sourceItemsSave->execute([
            $this->createSourceItem('SKU-1', 3, SourceItemInterface::STATUS_IN_STOCK),
            $this->createSourceItem('SKU-2', 8, SourceItemInterface::STATUS_IN_STOCK),
        ]);

        $writesAfter = $this->getLegacyWriteCount();
        self::assertSame($writesBefore, $writesAfter);
    }

    /**
     * @return int
     */
    private function getLegacyWriteCount(): int
    {
        $table = $this->connection->getTableName('inventory_legacy_stock_write');

        return (int)$this->connection->fetchOne('SELECT COALESCE(SUM(write_count), 0) FROM ' . $table);
    }

    /**
     * @return array
     */
    private function getSourceItemRows(): array
    {
        $rows = $this->connection->fetchAll(
            $this->connection->select()
                ->from($this->connection->getTableName('inventory_source_item'))
                ->where('source_code = ?', $this->defaultSourceCode)
        );

        return array_column($rows, null, 'sku');
    }

    /**
     * @return array
     */
    private function getSourceItemIds(): array
    {
        return array_map('intval', array_column($this->getSourceItemRows(), 'source_item_id', 'sku'));
    }

    /**
     * @param string $sku
     * @param float $quantity
     * @param int $status
     * @return SourceItemInterface
     */
    private function createSourceItem(string $sku, float $quantity, int $status): SourceItemInterface
    {
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode($this->defaultSourceCode);
        $sourceItem->setSku($sku);
        $sourceItem->setQuantity($quantity);
        $sourceItem->setStatus($status);

        return $sourceItem;
    }
}
