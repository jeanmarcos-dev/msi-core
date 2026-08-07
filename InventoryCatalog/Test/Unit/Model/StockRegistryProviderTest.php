<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\CatalogInventory\Api\Data\StockInterfaceFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\Data\StockStatusInterfaceFactory;
use Magento\CatalogInventory\Api\StockCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockRepositoryInterface;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\CatalogInventory\Model\Stock\Status;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryCatalog\Model\ResourceModel\GetStockQuantityBySkuList;
use Magento\InventoryCatalog\Model\StockRegistryProvider;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryConfiguration\Model\GetStockItemsConfigurationInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class StockRegistryProviderTest extends TestCase
{
    private const PRODUCT_ID = 10;
    private const SKU = 'SKU-1';
    private const STOCK_ID = 1;
    private const SCOPE_ID = 0;

    /**
     * @var GetStockItemDataInterface|MockObject
     */
    private $getStockItemData;

    /**
     * @var GetStockQuantityBySkuList|MockObject
     */
    private $getStockQuantityBySkuList;

    /**
     * @var array
     */
    private $quantityBySku = [];

    /**
     * @var bool
     */
    private $sourceItemManagementAllowed = true;

    /**
     * @var StockRegistryProvider
     */
    private $provider;

    protected function setUp(): void
    {
        $this->getStockItemData = $this->createMock(GetStockItemDataInterface::class);
        $this->getStockQuantityBySkuList = $this->createMock(GetStockQuantityBySkuList::class);
        $this->getStockQuantityBySkuList->method('execute')->willReturnCallback(
            fn () => $this->quantityBySku
        );

        $getProductTypesBySkus = $this->createMock(GetProductTypesBySkusInterface::class);
        $getProductTypesBySkus->method('execute')->willReturn([self::SKU => 'simple']);
        $isSourceItemManagementAllowed = $this->createMock(
            IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $isSourceItemManagementAllowed->method('execute')->willReturnCallback(
            fn () => $this->sourceItemManagementAllowed
        );

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(self::STOCK_ID);
        $stockByWebsiteIdResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockByWebsiteIdResolver->method('execute')->willReturn($stock);

        $getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $getSkusByProductIds->method('execute')->willReturnCallback(
            fn (array $productIds) => array_fill_keys($productIds, self::SKU)
        );

        $stockItemFactory = $this->createMock(StockItemInterfaceFactory::class);
        $stockItemFactory->method('create')->willReturnCallback(
            function (array $arguments = []) {
                $item = $this->createPartialMock(Item::class, ['setOrigData']);
                $item->setData($arguments['data'] ?? []);

                return $item;
            }
        );

        $stockStatusFactory = $this->createMock(StockStatusInterfaceFactory::class);
        $stockStatusFactory->method('create')->willReturnCallback(
            fn () => $this->createPartialMock(Status::class, [])
        );

        $configuration = $this->createPartialMock(Item::class, ['setOrigData']);
        $configuration->setData(['manage_stock' => 1, 'is_in_stock' => 1]);
        $getStockItemsConfiguration = $this->createMock(GetStockItemsConfigurationInterface::class);
        $getStockItemsConfiguration->method('execute')->willReturn([self::SKU => $configuration]);

        $this->provider = new StockRegistryProvider(
            $this->createMock(StockRepositoryInterface::class),
            $this->createMock(StockInterfaceFactory::class),
            $this->createMock(StockCriteriaInterfaceFactory::class),
            $stockItemFactory,
            $stockStatusFactory,
            $this->createMock(StockRegistryStorage::class),
            $getSkusByProductIds,
            $getStockItemsConfiguration,
            $this->getStockItemData,
            $stockByWebsiteIdResolver,
            $this->getStockQuantityBySkuList,
            $getProductTypesBySkus,
            $isSourceItemManagementAllowed
        );
    }

    public function testItReportsTheConfiguredStatusAndTheQuantityTheSourcesHold(): void
    {
        $this->quantityBySku = [self::SKU => 5.5];

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertSame(5.5, $stockItem->getQty());
        self::assertTrue($stockItem->getIsInStock());
    }

    /**
     * A product held below the out of stock threshold is unsalable while still configured In Stock. Reading
     * the salability back here would let the legacy save path persist it as the merchant's own intent.
     */
    public function testAnUnsalableProductStillReportsTheConfiguredStatus(): void
    {
        $this->quantityBySku = [self::SKU => 3.0];
        $this->getStockItemData->method('execute')->willReturn([
            GetStockItemDataInterface::QUANTITY => 0.0,
            GetStockItemDataInterface::IS_SALABLE => 0,
        ]);

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertSame(3.0, $stockItem->getQty());
        self::assertTrue($stockItem->getIsInStock());
    }

    /**
     * Nothing stocks the product on this stock any more, so there is no configured intent left to honour.
     */
    public function testASkuWithoutSourceItemsReadsAsOutOfStock(): void
    {
        $this->quantityBySku = [];

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertSame(0.0, $stockItem->getQty());
        self::assertFalse($stockItem->getIsInStock());
    }

    /**
     * The legacy save path treats a modified stock item as the caller's intent and lets it win over the
     * data the caller passed, so an item that was merely read out of MSI must not look modified.
     */
    public function testTheStockItemDoesNotLookModified(): void
    {
        $this->quantityBySku = [self::SKU => 5.5];

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertFalse($stockItem->hasDataChanges());
    }

    /**
     * A composite is stocked through its children and carries no source items of its own, so their
     * absence must not read as a stock status it never had.
     */
    public function testACompositeWithoutSourceItemsKeepsItsConfiguredStatus(): void
    {
        $this->quantityBySku = [];
        $this->sourceItemManagementAllowed = false;

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertSame(0.0, $stockItem->getQty());
        self::assertTrue($stockItem->getIsInStock());
    }

    public function testTheStockItemAndTheStockStatusAgreeAboutASkuNothingStocks(): void
    {
        $this->quantityBySku = [];
        $this->getStockItemData->method('execute')->willReturn(null);

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);
        $stockStatus = $this->provider->getStockStatus(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertSame((int) $stockItem->getIsInStock(), (int) $stockStatus->getStockStatus());
        self::assertSame($stockItem->getQty(), (float) $stockStatus->getQty());
    }

    public function testItResolvesAWholeListWithoutReadingProductByProduct(): void
    {
        $this->quantityBySku = [self::SKU => 4.0];
        $this->getStockQuantityBySkuList->expects(self::once())->method('execute');
        $this->getStockItemData->expects(self::never())->method('execute');

        $stockItems = $this->provider->getStockItems([self::PRODUCT_ID, self::PRODUCT_ID + 1], self::SCOPE_ID);

        self::assertSame([self::PRODUCT_ID, self::PRODUCT_ID + 1], array_keys($stockItems));
        self::assertEquals(4.0, $stockItems[self::PRODUCT_ID]->getQty());
        self::assertTrue((bool)$stockItems[self::PRODUCT_ID]->getIsInStock());
    }

    public function testAListOfProductsThatNoLongerExistIsEmpty(): void
    {
        $this->getStockQuantityBySkuList->expects(self::never())->method('execute');

        self::assertSame([], $this->provider->getStockItems([], self::SCOPE_ID));
    }
}
