<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\CatalogInventory\Model\Stock\Item;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalog\Model\GetDefaultSourceItemBySku;
use Magento\InventoryCatalog\Model\UpdateSourceItemBasedOnLegacyStockItem;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateSourceItemBasedOnLegacyStockItemTest extends TestCase
{
    private const PRODUCT_ID = 42;
    private const SKU = 'sku-42';

    /**
     * @var SourceItemInterfaceFactory|MockObject
     */
    private $sourceItemFactory;

    /**
     * @var SourceItemsSaveInterface|MockObject
     */
    private $sourceItemsSave;

    /**
     * @var GetDefaultSourceItemBySku|MockObject
     */
    private $getDefaultSourceItemBySku;

    /**
     * @var UpdateSourceItemBasedOnLegacyStockItem
     */
    private $model;

    protected function setUp(): void
    {
        $this->sourceItemFactory = $this->createMock(SourceItemInterfaceFactory::class);
        $this->sourceItemsSave = $this->createMock(SourceItemsSaveInterface::class);
        $this->getDefaultSourceItemBySku = $this->createMock(GetDefaultSourceItemBySku::class);

        $defaultSourceProvider = $this->createMock(DefaultSourceProviderInterface::class);
        $defaultSourceProvider->method('getCode')->willReturn('default');

        $getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $getSkusByProductIds->method('execute')->with([self::PRODUCT_ID])
            ->willReturn([self::PRODUCT_ID => self::SKU]);

        $this->model = new UpdateSourceItemBasedOnLegacyStockItem(
            $this->sourceItemFactory,
            $this->sourceItemsSave,
            $defaultSourceProvider,
            $getSkusByProductIds,
            $this->getDefaultSourceItemBySku
        );
    }

    /**
     * A stock item as the registry provider hands it out: hydrated from MSI, then snapshotted.
     *
     * @param array $data
     * @return Item|MockObject
     */
    private function hydratedStockItem(array $data)
    {
        $stockItem = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        // manage_stock has to be on: Item::getIsInStock() short-circuits to true without it.
        $stockItem->setData($data + ['product_id' => self::PRODUCT_ID, 'manage_stock' => 1]);
        $stockItem->setOrigData();

        return $stockItem;
    }

    public function testItIgnoresASaveThatOnlyTouchedTheConfiguration(): void
    {
        $this->getDefaultSourceItemBySku->method('execute')
            ->willReturn($this->createMock(SourceItemInterface::class));

        $stockItem = $this->hydratedStockItem(['qty' => 100.0, 'is_in_stock' => 1, 'min_qty' => 0]);
        $stockItem->setMinQty(5);

        $this->sourceItemsSave->expects(self::never())->method('execute');

        $this->model->execute($stockItem);
    }

    public function testItWritesOnlyTheQuantityWhenOnlyTheQuantityChanged(): void
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->expects(self::once())->method('setQuantity')->with(7.0);
        $sourceItem->expects(self::never())->method('setStatus');
        $this->getDefaultSourceItemBySku->method('execute')->willReturn($sourceItem);

        $stockItem = $this->hydratedStockItem(['qty' => 100.0, 'is_in_stock' => 1]);
        $stockItem->setQty(7.0);

        $this->sourceItemsSave->expects(self::once())->method('execute')->with([$sourceItem]);

        $this->model->execute($stockItem);
    }

    public function testItWritesOnlyTheStatusWhenOnlyTheStatusChanged(): void
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->expects(self::never())->method('setQuantity');
        $sourceItem->expects(self::once())->method('setStatus')->with(0);
        $this->getDefaultSourceItemBySku->method('execute')->willReturn($sourceItem);

        $stockItem = $this->hydratedStockItem(['qty' => 100.0, 'is_in_stock' => 1]);
        $stockItem->setIsInStock(0);

        $this->sourceItemsSave->expects(self::once())->method('execute')->with([$sourceItem]);

        $this->model->execute($stockItem);
    }

    /**
     * Import and product-create build the stock item themselves, so there is no snapshot to diff against and
     * the values have to be taken as given.
     */
    public function testItTakesACallerBuiltStockItemAtFaceValue(): void
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->expects(self::once())->method('setQuantity')->with(12.0);
        $sourceItem->expects(self::once())->method('setStatus')->with(1);
        $this->getDefaultSourceItemBySku->method('execute')->willReturn($sourceItem);

        $stockItem = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $stockItem->setData(
            ['product_id' => self::PRODUCT_ID, 'qty' => 12.0, 'is_in_stock' => 1, 'manage_stock' => 1]
        );

        $this->sourceItemsSave->expects(self::once())->method('execute')->with([$sourceItem]);

        $this->model->execute($stockItem);
    }

    public function testItCreatesTheDefaultSourceItemWithBothFieldsWhenNoneExists(): void
    {
        $sourceItem = $this->createMock(SourceItemInterface::class);
        $sourceItem->expects(self::once())->method('setSourceCode')->with('default');
        $sourceItem->expects(self::once())->method('setSku')->with(self::SKU);
        $sourceItem->expects(self::once())->method('setQuantity')->with(100.0);
        $sourceItem->expects(self::once())->method('setStatus')->with(1);

        $this->getDefaultSourceItemBySku->method('execute')->willReturn(null);
        $this->sourceItemFactory->method('create')->willReturn($sourceItem);

        $stockItem = $this->hydratedStockItem(['qty' => 100.0, 'is_in_stock' => 1]);

        $this->sourceItemsSave->expects(self::once())->method('execute')->with([$sourceItem]);

        $this->model->execute($stockItem);
    }
}
