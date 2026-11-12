<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Test\Unit\Model;

use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Exception\SkuIsNotAssignedToStockException;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\InventorySalesAdminUi\Model\ResourceModel\GetAssignedStockIdsBySku;
use Magento\InventorySalesAdminUi\Model\ResourceModel\GetStockNamesByIds;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetSalableQuantityDataBySkuTest extends TestCase
{
    /**
     * @var GetProductSalableQtyInterface|MockObject
     */
    private $getProductSalableQtyMock;

    /**
     * @var StockRepositoryInterface|MockObject
     */
    private $stockRepositoryMock;

    /**
     * @var GetAssignedStockIdsBySku|MockObject
     */
    private $getAssignedStockIdsBySkuMock;

    /**
     * @var GetStockItemConfigurationInterface|MockObject
     */
    private $getStockItemConfigurationMock;

    /**
     * @var GetStockNamesByIds|MockObject
     */
    private $getStockNamesByIdsMock;

    /**
     * @var GetSalableQuantityDataBySku
     */
    private $getSalableQuantityDataBySku;

    protected function setUp(): void
    {
        $this->getProductSalableQtyMock = $this->createMock(GetProductSalableQtyInterface::class);
        $this->stockRepositoryMock = $this->createMock(StockRepositoryInterface::class);
        $this->getAssignedStockIdsBySkuMock = $this->createMock(GetAssignedStockIdsBySku::class);
        $this->getStockItemConfigurationMock = $this->createMock(GetStockItemConfigurationInterface::class);
        $this->getStockNamesByIdsMock = $this->createMock(GetStockNamesByIds::class);

        $this->getSalableQuantityDataBySku = new GetSalableQuantityDataBySku(
            $this->getProductSalableQtyMock,
            $this->stockRepositoryMock,
            $this->getAssignedStockIdsBySkuMock,
            $this->getStockItemConfigurationMock,
            $this->getStockNamesByIdsMock
        );
    }

    public function testExecute(): void
    {
        $sku = 'product1';

        $stockItemConfigurationMock = $this->createMock(StockItemConfigurationInterface::class);
        $this->getStockItemConfigurationMock->expects(self::exactly(2))
            ->method('execute')
            ->willReturnMap([[$sku, 2, $stockItemConfigurationMock], [$sku, 3, $stockItemConfigurationMock]]);
        $stockItemConfigurationMock->expects(self::exactly(2))
            ->method('isManageStock')
            ->willReturn(true);
        $this->getAssignedStockIdsBySkuMock->expects(self::once())
            ->method('execute')
            ->willReturn([2, 3]);
        $this->getStockNamesByIdsMock->expects(self::once())
            ->method('execute')
            ->willReturn([2 => 'Stock 2', 3 => 'Stock 3']);
        $this->getProductSalableQtyMock->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(function ($arg1, $arg2) use ($sku) {
                if ($arg1 == $sku && $arg2 == 2) {
                    return 200;
                } elseif ($arg1 == $sku && $arg2 == 3) {
                    return 300;
                }
            });

        $data = $this->getSalableQuantityDataBySku->execute($sku);
        $expectedData = [
            [
                'stock_id' => 2,
                'stock_name' => 'Stock 2',
                'qty' => 200,
                'manage_stock' => true,
            ],
            [
                'stock_id' => 3,
                'stock_name' => 'Stock 3',
                'qty' => 300,
                'manage_stock' => true,
            ],
        ];
        self::assertEquals($expectedData, $data);
    }

    public function testExecuteWithDisabledManageStock(): void
    {
        $sku = 'product2';

        $stockItemConfigurationMock = $this->createMock(StockItemConfigurationInterface::class);
        $this->getStockItemConfigurationMock->expects(self::exactly(2))
            ->method('execute')
            ->willReturnMap([[$sku, 4, $stockItemConfigurationMock], [$sku, 5, $stockItemConfigurationMock]]);
        $stockItemConfigurationMock->expects(self::exactly(2))
            ->method('isManageStock')
            ->willReturn(false);
        $this->getAssignedStockIdsBySkuMock->expects(self::once())
            ->method('execute')
            ->willReturn([4, 5]);
        $this->getStockNamesByIdsMock->expects(self::once())
            ->method('execute')
            ->willReturn([4 => 'Stock 4', 5 => 'Stock 5']);
        $this->getProductSalableQtyMock->expects(self::never())
            ->method('execute');

        $data = $this->getSalableQuantityDataBySku->execute($sku);
        $expectedData = [
            [
                'stock_id' => 4,
                'stock_name' => 'Stock 4',
                'qty' => null,
                'manage_stock' => false,
            ],
            [
                'stock_id' => 5,
                'stock_name' => 'Stock 5',
                'qty' => null,
                'manage_stock' => false,
            ],
        ];
        self::assertEquals($expectedData, $data);
    }

    public function testReadsManageStockFromTheStockOfEachRow(): void
    {
        $sku = 'only-in-stock-2';

        $stockItemConfigurationMock = $this->createStub(StockItemConfigurationInterface::class);
        $stockItemConfigurationMock->method('isManageStock')->willReturn(true);
        $this->getStockItemConfigurationMock->method('execute')->willReturnCallback(
            function (string $requestedSku, int $stockId) use ($sku, $stockItemConfigurationMock) {
                if ($requestedSku === $sku && $stockId === 2) {
                    return $stockItemConfigurationMock;
                }
                throw new SkuIsNotAssignedToStockException(__('The requested sku is not assigned to given stock.'));
            }
        );
        $this->getAssignedStockIdsBySkuMock->method('execute')->with($sku)->willReturn([2]);
        $this->getStockNamesByIdsMock->method('execute')->willReturn([2 => 'Stock 2']);
        $this->getProductSalableQtyMock->method('execute')->with($sku, 2)->willReturn(7.0);

        self::assertEquals(
            [['stock_id' => 2, 'stock_name' => 'Stock 2', 'qty' => 7.0, 'manage_stock' => true]],
            $this->getSalableQuantityDataBySku->execute($sku)
        );
    }
}
