<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Catalog\Test\Fixture\Product;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Model\IsProductSalable;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorage;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Saving a default source item must drive the salability of the Default Stock through the MSI index.
 */
class DefaultSourceItemsSaveSalabilityTest extends TestCase
{
    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemsSave;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /**
     * @var GetStockItemDataInterface
     */
    private $getStockItemData;

    /**
     * @var IndexerRegistry
     */
    private $indexerRegistry;

    /**
     * @var IsProductSalable
     */
    private $isProductSalable;

    /**
     * @var DataFixtureStorage
     */
    private $fixtures;

    protected function setUp(): void
    {
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        $this->sourceItemRepository = Bootstrap::getObjectManager()->get(SourceItemRepositoryInterface::class);
        $this->sourceItemsSave = Bootstrap::getObjectManager()->get(SourceItemsSaveInterface::class);
        $this->defaultSourceProvider = Bootstrap::getObjectManager()->get(DefaultSourceProviderInterface::class);
        $this->getStockItemData = Bootstrap::getObjectManager()->get(GetStockItemDataInterface::class);
        $this->indexerRegistry = Bootstrap::getObjectManager()->get(IndexerRegistry::class);
        $this->isProductSalable = Bootstrap::getObjectManager()->get(IsProductSalable::class);
        $this->fixtures = DataFixtureStorageManager::getStorage();
    }

    /**
     * @return void
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testIndexedOnUpdate(): void
    {
        $productSku = 'SKU-1';

        $indexData = $this->getStockItemData->execute($productSku, Stock::DEFAULT_STOCK_ID);
        self::assertEquals(1, $indexData[GetStockItemDataInterface::IS_SALABLE]);
        self::assertEquals(5.5, $indexData[GetStockItemDataInterface::QUANTITY]);

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, $productSku)
            ->addFilter(SourceItemInterface::SOURCE_CODE, $this->defaultSourceProvider->getCode())
            ->create();
        $sourceItems = $this->sourceItemRepository->getList($searchCriteria)->getItems();
        self::assertCount(1, $sourceItems);

        $sourceItem = reset($sourceItems);
        $sourceItem->setQuantity(20);
        $this->sourceItemsSave->execute($sourceItems);

        $indexData = $this->getStockItemData->execute($productSku, Stock::DEFAULT_STOCK_ID);
        self::assertEquals(1, $indexData[GetStockItemDataInterface::IS_SALABLE]);
        self::assertEquals(20, $indexData[GetStockItemDataInterface::QUANTITY]);

        $sourceItem->setStatus(SourceItemInterface::STATUS_OUT_OF_STOCK);
        $this->sourceItemsSave->execute($sourceItems);

        // An out of stock source contributes nothing to the stock, so the indexed quantity drops to zero
        // even though the source item still carries 20.
        $indexData = $this->getStockItemData->execute($productSku, Stock::DEFAULT_STOCK_ID);
        self::assertEquals(0, $indexData[GetStockItemDataInterface::IS_SALABLE]);
        self::assertEquals(0, $indexData[GetStockItemDataInterface::QUANTITY]);
    }

    /**
     * A scheduled indexer must not change the outcome, the index is updated by the source item save itself.
     *
     * @return void
     * @magentoDbIsolation disabled
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testWithScheduledIndexer(): void
    {
        $indexer = $this->indexerRegistry->get(InventoryIndexer::INDEXER_ID);
        $indexer->setScheduled(true);

        try {
            $this->testIndexedOnUpdate();
        } finally {
            $indexer->setScheduled(false);
        }
    }

    #[
        DataFixture(Product::class, ['stock_item' => ['qty' => 0]], 'product')
    ]
    public function testProductWithQtyZeroShouldBeOutOfStock(): void
    {
        $product = $this->fixtures->get('product');
        $this->assertFalse($this->isProductSalable->execute($product->getSku(), Stock::DEFAULT_STOCK_ID));
    }
}
