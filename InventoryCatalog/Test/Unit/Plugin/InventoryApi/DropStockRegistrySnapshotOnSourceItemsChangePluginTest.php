<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\InventoryApi;

use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalog\Plugin\InventoryApi\DropStockRegistrySnapshotOnSourceItemsChangePlugin;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DropStockRegistrySnapshotOnSourceItemsChangePluginTest extends TestCase
{
    /**
     * @var GetProductIdsBySkusInterface|MockObject
     */
    private $getProductIdsBySkus;

    /**
     * @var StockRegistryStorage|MockObject
     */
    private $stockRegistryStorage;

    /**
     * @var DropStockRegistrySnapshotOnSourceItemsChangePlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->getProductIdsBySkus = $this->createMock(GetProductIdsBySkusInterface::class);
        $this->stockRegistryStorage = $this->createMock(StockRegistryStorage::class);
        $this->plugin = new DropStockRegistrySnapshotOnSourceItemsChangePlugin(
            $this->getProductIdsBySkus,
            $this->stockRegistryStorage
        );
    }

    public function testItDropsTheSnapshotOfEveryProductWhoseSourceItemsChanged(): void
    {
        $this->getProductIdsBySkus->expects(self::once())
            ->method('execute')
            ->with(['SKU-1', 'SKU-2'])
            ->willReturn(['SKU-1' => 10, 'SKU-2' => 20]);

        $removedItems = [];
        $removedStatuses = [];
        $this->stockRegistryStorage->method('removeStockItem')
            ->willReturnCallback(function ($productId) use (&$removedItems) {
                $removedItems[] = $productId;
            });
        $this->stockRegistryStorage->method('removeStockStatus')
            ->willReturnCallback(function ($productId) use (&$removedStatuses) {
                $removedStatuses[] = $productId;
            });

        $this->plugin->afterExecute(
            $this->createMock(SourceItemsSaveInterface::class),
            null,
            [$this->sourceItem('SKU-1'), $this->sourceItem('SKU-2')]
        );

        self::assertSame([10, 20], $removedItems);
        self::assertSame([10, 20], $removedStatuses);
    }

    /**
     * The same product may be stocked at several sources, and one removal covers them all.
     */
    public function testItResolvesEachSkuOnce(): void
    {
        $this->getProductIdsBySkus->expects(self::once())
            ->method('execute')
            ->with(['SKU-1'])
            ->willReturn(['SKU-1' => 10]);
        $this->stockRegistryStorage->expects(self::once())->method('removeStockItem')->with(10);
        $this->stockRegistryStorage->expects(self::once())->method('removeStockStatus')->with(10);

        $this->plugin->afterExecute(
            $this->createMock(SourceItemsSaveInterface::class),
            null,
            [$this->sourceItem('SKU-1'), $this->sourceItem('SKU-1')]
        );
    }

    public function testItDropsEverythingWhenASkuIsNotInTheCatalog(): void
    {
        $this->getProductIdsBySkus->method('execute')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->stockRegistryStorage->expects(self::once())->method('clean');
        $this->stockRegistryStorage->expects(self::never())->method('removeStockItem');

        $this->plugin->afterExecute(
            $this->createMock(SourceItemsSaveInterface::class),
            null,
            [$this->sourceItem('SKU-GONE')]
        );
    }

    public function testItLeavesTheSnapshotAloneWhenNothingChanged(): void
    {
        $this->getProductIdsBySkus->expects(self::never())->method('execute');
        $this->stockRegistryStorage->expects(self::never())->method('clean');
        $this->stockRegistryStorage->expects(self::never())->method('removeStockItem');

        $this->plugin->afterExecute($this->createMock(SourceItemsSaveInterface::class), null, []);
    }

    /**
     * @param string $sku
     * @return SourceItemInterface|MockObject
     */
    private function sourceItem(string $sku)
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->method('getSku')->willReturn($sku);

        return $sourceItem;
    }
}
