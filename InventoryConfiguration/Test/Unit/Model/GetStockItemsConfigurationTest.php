<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Test\Unit\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\InventoryConfiguration\Model\GetStockItemsConfiguration;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetStockItemsConfigurationTest extends TestCase
{
    /**
     * @var StockItemInterfaceFactory|MockObject
     */
    private $stockItemFactoryMock;

    /**
     * @var StockItemConfigurationResource|MockObject
     */
    private $resourceMock;

    /**
     * @var GetStockItemsConfiguration
     */
    private $model;

    protected function setUp(): void
    {
        $this->stockItemFactoryMock = $this->getMockBuilder(StockItemInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->resourceMock = $this->createMock(StockItemConfigurationResource::class);
        $this->model = new GetStockItemsConfiguration($this->stockItemFactoryMock, $this->resourceMock);
    }

    public function testRowsAreHydratedThroughTheFactoryAndIndexedBySku(): void
    {
        $this->resourceMock->method('get')->with(['SKU-1'])->willReturn([
            'SKU-1' => ['sku' => 'SKU-1', 'manage_stock' => 1],
        ]);
        $stockItem = $this->createMock(StockItemInterface::class);
        $this->stockItemFactoryMock->expects(self::once())
            ->method('create')
            ->with(['data' => ['sku' => 'SKU-1', 'manage_stock' => 1]])
            ->willReturn($stockItem);

        self::assertSame(['SKU-1' => $stockItem], $this->model->execute(['SKU-1']));
    }

    public function testSkusWithoutAStoredRowAreAbsentFromTheResult(): void
    {
        $this->resourceMock->method('get')->willReturn([]);
        $this->stockItemFactoryMock->expects(self::never())->method('create');

        self::assertSame([], $this->model->execute(['SKU-1']));
    }
}
