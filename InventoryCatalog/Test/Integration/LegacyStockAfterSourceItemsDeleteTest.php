<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\Stock\Status;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Deleting the source items of a product must leave the legacy CatalogInventory contract telling the
 * truth. Nothing writes cataloginventory_stock_item or cataloginventory_stock_status any more, so what
 * is asserted here is the registry that stands in front of them, which MSI answers.
 *
 * The Default Stock is an ordinary MSI stock now, so reindexing it builds and swaps a real
 * index table. That is DDL, and DDL cannot run inside the transaction the framework wraps a
 * test in — the sibling tests for a non-default stock have always disabled isolation for the
 * same reason.
 *
 * @magentoDbIsolation disabled
 */
class LegacyStockAfterSourceItemsDeleteTest extends TestCase
{
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var SourceItemsDeleteInterface
     */
    private $sourceItemsDelete;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    protected function setUp(): void
    {
        $this->productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
        $this->stockRegistry = Bootstrap::getObjectManager()->get(StockRegistryInterface::class);
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->get(SearchCriteriaBuilder::class);
        $this->sourceItemRepository = Bootstrap::getObjectManager()->get(SourceItemRepositoryInterface::class);
        $this->sourceItemsDelete = Bootstrap::getObjectManager()->get(SourceItemsDeleteInterface::class);
        $this->defaultSourceProvider = Bootstrap::getObjectManager()->get(DefaultSourceProviderInterface::class);
    }

    /**
     * @magentoDataFixture Magento_InventoryApi::Test/_files/products.php
     * @magentoDataFixture Magento_InventoryCatalog::Test/_files/source_items_on_default_source.php
     * @magentoDataFixture Magento_InventoryIndexer::Test/_files/reindex_inventory.php
     */
    public function testSetOutOfStock()
    {
        $productSku = 'SKU-1';
        $productId = (int) $this->productRepository->get($productSku)->getId();

        $legacyStockItem = $this->stockRegistry->getStockItem($productId);
        self::assertTrue((bool) $legacyStockItem->getIsInStock());
        self::assertEquals(5.5, $legacyStockItem->getQty());

        $legacyStockStatus = $this->stockRegistry->getStockStatus($productId);
        self::assertEquals(Status::STATUS_IN_STOCK, $legacyStockStatus->getStockStatus());
        self::assertEquals(5.5, $legacyStockStatus->getQty());

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SourceItemInterface::SKU, $productSku)
            ->addFilter(SourceItemInterface::SOURCE_CODE, $this->defaultSourceProvider->getCode())
            ->create();
        $sourceItems = $this->sourceItemRepository->getList($searchCriteria)->getItems();
        self::assertCount(1, $sourceItems);

        $this->sourceItemsDelete->execute($sourceItems);

        $legacyStockItem = $this->stockRegistry->getStockItem($productId);
        self::assertFalse((bool) $legacyStockItem->getIsInStock());
        self::assertEquals(0, $legacyStockItem->getQty());

        $legacyStockStatus = $this->stockRegistry->getStockStatus($productId);
        self::assertEquals(Status::STATUS_OUT_OF_STOCK, $legacyStockStatus->getStockStatus());
        self::assertEquals(0, $legacyStockStatus->getQty());
    }
}
