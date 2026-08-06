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
use Magento\InventoryCatalog\Model\StockRegistryProvider;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
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
     * @var StockRegistryProvider
     */
    private $provider;

    protected function setUp(): void
    {
        $this->getStockItemData = $this->createMock(GetStockItemDataInterface::class);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(self::STOCK_ID);
        $stockByWebsiteIdResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockByWebsiteIdResolver->method('execute')->willReturn($stock);

        $getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $getSkusByProductIds->method('execute')->willReturn([self::PRODUCT_ID => self::SKU]);

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

        // Item::getIsInStock() short-circuits to true for an unmanaged product, so the configuration the
        // stock item is seeded from has to manage stock for the flag under test to be reachable at all.
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
            $stockByWebsiteIdResolver
        );
    }

    public function testItReportsTheIndexedQuantityAndStatus(): void
    {
        $this->getStockItemData->method('execute')->willReturn([
            GetStockItemDataInterface::QUANTITY => 5.5,
            GetStockItemDataInterface::IS_SALABLE => 1,
        ]);

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertSame(5.5, $stockItem->getQty());
        self::assertTrue($stockItem->getIsInStock());
    }

    /**
     * A sku the index does not carry is stocked nowhere in the stock, whatever the configuration row
     * that seeds the rest of the stock item still says about it.
     */
    public function testAnUnindexedSkuReadsAsOutOfStock(): void
    {
        $this->getStockItemData->method('execute')->willReturn(null);

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
        $this->getStockItemData->method('execute')->willReturn([
            GetStockItemDataInterface::QUANTITY => 5.5,
            GetStockItemDataInterface::IS_SALABLE => 1,
        ]);

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertFalse($stockItem->hasDataChanges());
    }

    public function testTheStockItemAndTheStockStatusAgreeAboutAnUnindexedSku(): void
    {
        $this->getStockItemData->method('execute')->willReturn(null);

        $stockItem = $this->provider->getStockItem(self::PRODUCT_ID, self::SCOPE_ID);
        $stockStatus = $this->provider->getStockStatus(self::PRODUCT_ID, self::SCOPE_ID);

        self::assertSame((int) $stockItem->getIsInStock(), (int) $stockStatus->getStockStatus());
        self::assertSame($stockItem->getQty(), (float) $stockStatus->getQty());
    }
}
