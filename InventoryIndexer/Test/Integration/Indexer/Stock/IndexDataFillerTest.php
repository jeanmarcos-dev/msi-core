<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Integration\Indexer\Stock;

use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryApi\Test\Fixture\Source as SourceFixture;
use Magento\InventoryApi\Test\Fixture\SourceItems as SourceItemsFixture;
use Magento\InventoryApi\Test\Fixture\Stock as StockFixture;
use Magento\InventoryApi\Test\Fixture\StockSourceLinks as StockSourceLinksFixture;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\SourceItem\SkuListInStockFactory;
use Magento\InventoryIndexer\Indexer\Stock\IndexDataFiller;
use Magento\InventoryIndexer\Model\ResourceModel\GetStockItemData;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexAlias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexHandlerInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\DbIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class IndexDataFillerTest extends TestCase
{
    #[
        DbIsolation(false),
        AppIsolation(true),
        DataFixture(SourceFixture::class, as: 'source2'),
        DataFixture(StockFixture::class, as: 'stock2'),
        DataFixture(
            StockSourceLinksFixture::class,
            [['stock_id' => '$stock2.stock_id$', 'source_code' => '$source2.source_code$']]
        ),
        DataFixture(ProductFixture::class, as: 'p1'),
        DataFixture(ProductFixture::class, as: 'p2'),
        DataFixture(
            SourceItemsFixture::class,
            [
                ['sku' => '$p1.sku$', 'source_code' => '$source2.source_code$', 'quantity' => 100],
                ['sku' => '$p2.sku$', 'source_code' => 'default', 'quantity' => 200],
            ]
        ),
    ]
    public function testFailedPartialReindexKeepsThePreviousRows(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $fixtures = DataFixtureStorageManager::getStorage();
        $customStockId = (int)$fixtures->get('stock2')->getStockId();
        $defaultStockId = $objectManager->get(DefaultStockProviderInterface::class)->getId();
        $customSku = $fixtures->get('p1')->getSku();
        $defaultSku = $fixtures->get('p2')->getSku();
        $getStockItemData = $objectManager->get(GetStockItemData::class);
        $expected = [
            [$customStockId, $customSku, 100],
            [$defaultStockId, $defaultSku, 200],
        ];

        foreach ($expected as [$stockId, $sku, $quantity]) {
            self::assertEquals(
                [GetStockItemDataInterface::QUANTITY => $quantity, GetStockItemDataInterface::IS_SALABLE => 1],
                $getStockItemData->execute($sku, $stockId)
            );
            $this->expectReindexToFailWithRowsKept($stockId, $sku, $quantity, $getStockItemData);
        }
    }

    private function expectReindexToFailWithRowsKept(
        int $stockId,
        string $sku,
        int $quantity,
        GetStockItemData $getStockItemData
    ): void {
        $objectManager = Bootstrap::getObjectManager();
        $failingHandler = new class ($objectManager->get(IndexHandlerInterface::class)) implements
            IndexHandlerInterface
        {
            public function __construct(private readonly IndexHandlerInterface $inner)
            {
            }

            public function cleanIndex(IndexName $indexName, \Traversable $documents, string $connectionName): void
            {
                $this->inner->cleanIndex($indexName, $documents, $connectionName);
            }

            public function saveIndex(IndexName $indexName, \Traversable $documents, string $connectionName): void
            {
                throw new \RuntimeException('write failed');
            }
        };
        $indexDataFiller = $objectManager->create(
            IndexDataFiller::class,
            ['indexStructureHandler' => $failingHandler]
        );
        $indexName = $objectManager->get(IndexNameBuilder::class)
            ->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string)$stockId)
            ->setAlias(IndexAlias::MAIN->value)
            ->build();
        $skuListInStock = $objectManager->get(SkuListInStockFactory::class)
            ->create(['stockId' => $stockId, 'skuList' => [$sku]]);

        try {
            $indexDataFiller->fillIndex($indexName, $skuListInStock, ResourceConnection::DEFAULT_CONNECTION);
            self::fail('The reindex must fail when the write fails');
        } catch (\RuntimeException $exception) {
            self::assertSame('write failed', $exception->getMessage());
        }

        self::assertEquals(
            [GetStockItemDataInterface::QUANTITY => $quantity, GetStockItemDataInterface::IS_SALABLE => 1],
            $getStockItemData->execute($sku, $stockId),
            "SKU {$sku} must keep its row in inventory_stock_{$stockId}"
        );
    }
}
