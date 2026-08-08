<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\CatalogInventory;

use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as ItemResourceModel;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryCatalog\Model\GetDefaultSourceItemBySku;
use Magento\InventoryCatalog\Model\UpdateSourceItemBasedOnLegacyStockItem;
use Magento\InventoryCatalog\Plugin\CatalogInventory\UpdateSourceItemAtLegacyStockItemSavePlugin;
use Magento\InventoryCatalogApi\Model\CompositeProductStockStatusProcessorInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryConfiguration\Model\LegacyStockItem\CacheStorage;
use Magento\InventoryConfiguration\Model\ProjectLegacyStockItemToConfiguration;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryIndexer\Model\ReindexSourceItemsBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateSourceItemAtLegacyStockItemSavePluginTest extends TestCase
{
    private const PRODUCT_ID = 42;
    private const SKU = 'sku-42';

    /**
     * @var StockRegistryStorage|MockObject
     */
    private $stockRegistryStorage;

    /**
     * @var UpdateSourceItemAtLegacyStockItemSavePlugin
     */
    private $plugin;

    /**
     * @var UpdateSourceItemBasedOnLegacyStockItem|MockObject
     */
    private $updateSourceItemBasedOnLegacyStockItem;

    /**
     * @var ReindexSourceItemsBySkus|MockObject
     */
    private $reindexSourceItemsBySkus;

    /**
     * @var ProjectLegacyStockItemToConfiguration|MockObject
     */
    private $projectLegacyStockItemToConfiguration;

    protected function setUp(): void
    {
        $this->stockRegistryStorage = $this->createMock(StockRegistryStorage::class);
        $this->updateSourceItemBasedOnLegacyStockItem = $this->createMock(
            UpdateSourceItemBasedOnLegacyStockItem::class
        );
        $this->reindexSourceItemsBySkus = $this->createMock(ReindexSourceItemsBySkus::class);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->createMock(AdapterInterface::class));

        $getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $getSkusByProductIds->method('execute')->willReturn([self::PRODUCT_ID => self::SKU]);

        $getProductTypesBySkus = $this->createMock(GetProductTypesBySkusInterface::class);
        $getProductTypesBySkus->method('execute')->willReturn([self::SKU => 'simple']);

        $isSourceItemManagementAllowed = $this->createMock(
            IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $isSourceItemManagementAllowed->method('execute')->willReturn(true);

        $isSingleSourceMode = $this->createMock(IsSingleSourceModeInterface::class);
        $isSingleSourceMode->method('execute')->willReturn(false);

        $this->projectLegacyStockItemToConfiguration = $this->createMock(
            ProjectLegacyStockItemToConfiguration::class
        );

        $this->plugin = new UpdateSourceItemAtLegacyStockItemSavePlugin(
            $this->updateSourceItemBasedOnLegacyStockItem,
            $resourceConnection,
            $isSourceItemManagementAllowed,
            $getProductTypesBySkus,
            $getSkusByProductIds,
            $this->createMock(GetDefaultSourceItemBySku::class),
            $this->createMock(CacheStorage::class),
            $this->createMock(CompositeProductStockStatusProcessorInterface::class),
            $isSingleSourceMode,
            $this->projectLegacyStockItemToConfiguration,
            $this->stockRegistryStorage,
            $this->reindexSourceItemsBySkus
        );
    }

    public function testItReindexesWhenTheSaveOnlyChangedTheConfiguration(): void
    {
        $this->projectLegacyStockItemToConfiguration->method('execute')->willReturn(true);
        $this->updateSourceItemBasedOnLegacyStockItem->method('execute')->willReturn(false);
        $this->reindexSourceItemsBySkus->expects(self::once())->method('execute')->with([self::SKU]);

        $subject = $this->createMock(ItemResourceModel::class);
        $this->plugin->aroundSave($subject, fn () => $subject, $this->legacyStockItem());
    }

    public function testItDoesNotReindexWhenTheSaveTouchedNothingTheIndexReads(): void
    {
        $this->projectLegacyStockItemToConfiguration->method('execute')->willReturn(false);
        $this->updateSourceItemBasedOnLegacyStockItem->method('execute')->willReturn(false);
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $subject = $this->createMock(ItemResourceModel::class);
        $this->plugin->aroundSave($subject, fn () => $subject, $this->legacyStockItem());
    }

    public function testItLeavesTheReindexToTheSourceItemSaveThatAlreadyRanOne(): void
    {
        $this->projectLegacyStockItemToConfiguration->method('execute')->willReturn(true);
        $this->updateSourceItemBasedOnLegacyStockItem->method('execute')->willReturn(true);
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $subject = $this->createMock(ItemResourceModel::class);
        $this->plugin->aroundSave($subject, fn () => $subject, $this->legacyStockItem());
    }

    public function testItDropsTheRegistrySnapshotOfTheSavedProduct(): void
    {
        $this->stockRegistryStorage->expects(self::once())->method('removeStockItem')->with(self::PRODUCT_ID);
        $this->stockRegistryStorage->expects(self::once())->method('removeStockStatus')->with(self::PRODUCT_ID);

        $subject = $this->createMock(ItemResourceModel::class);

        self::assertSame($subject, $this->plugin->aroundSave($subject, fn () => $subject, $this->legacyStockItem()));
    }

    /**
     * @return Item
     */
    private function legacyStockItem(): Item
    {
        $stockItem = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $stockItem->setData(['product_id' => self::PRODUCT_ID, 'qty' => 5.0, 'is_in_stock' => 1, 'manage_stock' => 1]);

        return $stockItem;
    }
}
