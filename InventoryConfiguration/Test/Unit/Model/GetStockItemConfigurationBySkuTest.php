<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryConfiguration\Model\GetStockItemConfigurationBySku;
use Magento\InventoryConfiguration\Model\GetStockItemsConfigurationInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetStockItemConfigurationBySkuTest extends TestCase
{
    /**
     * @var StockItemInterfaceFactory|MockObject
     */
    private $stockItemFactoryMock;

    /**
     * @var GetProductIdsBySkusInterface|MockObject
     */
    private $getProductIdsBySkusMock;

    /**
     * @var GetStockItemsConfigurationInterface|MockObject
     */
    private $getStockItemsConfigurationMock;

    /**
     * @var GetStockItemConfigurationBySku
     */
    private $model;

    protected function setUp(): void
    {
        $this->stockItemFactoryMock = $this->getMockBuilder(StockItemInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->getProductIdsBySkusMock = $this->createMock(GetProductIdsBySkusInterface::class);
        $this->getStockItemsConfigurationMock = $this->createMock(GetStockItemsConfigurationInterface::class);
        $this->model = new GetStockItemConfigurationBySku(
            $this->stockItemFactoryMock,
            $this->getProductIdsBySkusMock,
            $this->getStockItemsConfigurationMock
        );
    }

    public function testStoredConfigurationIsReturned(): void
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $this->getStockItemsConfigurationMock->method('execute')->with(['SKU-1'])
            ->willReturn(['SKU-1' => $stockItem]);

        self::assertSame($stockItem, $this->model->execute('SKU-1'));
    }

    public function testAnEmptyEntityIsReturnedWhenTheSkuHasNoStoredRow(): void
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->expects(self::never())->method('setManageStock');
        $this->getStockItemsConfigurationMock->method('execute')->willReturn([]);
        $this->stockItemFactoryMock->method('create')->with()->willReturn($stockItem);

        self::assertSame($stockItem, $this->model->execute('SKU-1'));
    }

    public function testStockIsManagedForSkusMissingFromTheCatalog(): void
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $this->getProductIdsBySkusMock->method('execute')
            ->willThrowException(new NoSuchEntityException(__('missing')));
        $this->getStockItemsConfigurationMock->expects(self::never())->method('execute');
        $this->stockItemFactoryMock->method('create')->willReturn($stockItem);
        $stockItem->expects(self::once())->method('setManageStock')->with(true);

        self::assertSame($stockItem, $this->model->execute('SKU-1'));
    }
}
