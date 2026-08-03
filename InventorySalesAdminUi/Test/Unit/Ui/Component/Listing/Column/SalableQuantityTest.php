<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventorySalesAdminUi\Model\AddSourceSalableQuantityBreakdown;
use Magento\InventorySalesAdminUi\Model\GetSalableQuantityDataBySku;
use Magento\InventorySalesAdminUi\Model\ResourceModel\GetAssignedStockIdsBySku;
use Magento\InventorySalesAdminUi\Ui\Component\Listing\Column\SalableQuantity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SalableQuantityTest extends TestCase
{
    /**
     * @var ContextInterface|MockObject
     */
    private $contextMock;

    /**
     * @var UiComponentFactory|MockObject
     */
    private $uiComponentFactoryMock;

    /**
     * @var IsSourceItemManagementAllowedForProductTypeInterface|MockObject
     */
    private $isSourceItemManagementAllowedForProductTypeMock;

    /**
     * @var GetSalableQuantityDataBySku|MockObject
     */
    private $getSalableQuantityDataBySkuMock;

    /**
     * @var GetAssignedStockIdsBySku|MockObject
     */
    private $getAssignedStockIdsBySkuMock;

    /**
     * @var AddSourceSalableQuantityBreakdown|MockObject
     */
    private $addSourceSalableQuantityBreakdownMock;

    /**
     * @var SalableQuantity
     */
    private $salableQuantity;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(ContextInterface::class);
        $this->uiComponentFactoryMock = $this->createMock(UiComponentFactory::class);
        $this->isSourceItemManagementAllowedForProductTypeMock = $this->createMock(
            IsSourceItemManagementAllowedForProductTypeInterface::class
        );
        $this->getSalableQuantityDataBySkuMock = $this->createMock(GetSalableQuantityDataBySku::class);
        $this->getAssignedStockIdsBySkuMock = $this->createMock(GetAssignedStockIdsBySku::class);
        $this->addSourceSalableQuantityBreakdownMock = $this->createMock(AddSourceSalableQuantityBreakdown::class);
        $this->addSourceSalableQuantityBreakdownMock->method('execute')->willReturnArgument(0);

        $this->salableQuantity = new SalableQuantity(
            $this->contextMock,
            $this->uiComponentFactoryMock,
            $this->isSourceItemManagementAllowedForProductTypeMock,
            $this->getSalableQuantityDataBySkuMock,
            $this->getAssignedStockIdsBySkuMock,
            $this->addSourceSalableQuantityBreakdownMock,
            2
        );
    }

    public function testPrepareDataSource(): void
    {
        $dataSource = [
            'data' => [
                'totalRecords' => 3,
                'items' => [
                    ['sku' => 'product1', 'type_id' => 'simple'],
                    ['sku' => 'product2', 'type_id' => 'simple'],
                    ['sku' => 'product3', 'type_id' => 'configurable'],
                ],
            ],
        ];

        $this->isSourceItemManagementAllowedForProductTypeMock->expects(self::exactly(3))
            ->method('execute')
            ->willReturnCallback(function ($arg) {
                if ($arg == 'simple') {
                    return true;
                } elseif ($arg == 'configurable') {
                    return false;
                }
            });
        $this->getAssignedStockIdsBySkuMock->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(function ($arg) {
                if ($arg == 'product1') {
                    return [2,3];
                } elseif ($arg == 'product2') {
                    return [2,3,4];
                }
            });

        $this->getSalableQuantityDataBySkuMock->expects(self::once())
            ->method('execute')
            ->with('product1')
            ->willReturn(
                [
                    ['stock_id' => 2, 'stock_name' => 'Stock 2', 'qty' => 200, 'manage_stock' => true],
                    ['stock_id' => 3, 'stock_name' => 'Stock 3', 'qty' => 300, 'manage_stock' => true],
                ]
            );

        $dataSource = $this->salableQuantity->prepareDataSource($dataSource);
        $expectedDataSource = [
            'data' => [
                'totalRecords' => 3,
                'items' => [
                    [
                        'sku' => 'product1',
                        'type_id' => 'simple',
                        'salable_quantity' => [
                            ['stock_id' => 2, 'stock_name' => 'Stock 2', 'qty' => 200, 'manage_stock' => true],
                            ['stock_id' => 3, 'stock_name' => 'Stock 3', 'qty' => 300, 'manage_stock' => true],
                        ],
                    ],
                    [
                        'sku' => 'product2',
                        'type_id' => 'simple',
                        'salable_quantity' => [
                            ['manage_stock' => true, 'message' => 'Associated to 3 stocks']
                        ],
                    ],
                    [
                        'sku' => 'product3',
                        'type_id' => 'configurable',
                        'salable_quantity' => [],
                    ],
                ],
            ],
        ];
        self::assertEquals($expectedDataSource, $dataSource);
    }

    public function testHandsEveryRowOfThePageToTheBreakdownInOneCall(): void
    {
        $this->isSourceItemManagementAllowedForProductTypeMock->method('execute')->willReturn(true);
        $this->getAssignedStockIdsBySkuMock->method('execute')->willReturn([2]);
        $stockEntries = [['stock_id' => 2, 'stock_name' => 'Stock 2', 'qty' => 1, 'manage_stock' => true]];
        $this->getSalableQuantityDataBySkuMock->method('execute')->willReturn($stockEntries);

        $this->addSourceSalableQuantityBreakdownMock = $this->createMock(AddSourceSalableQuantityBreakdown::class);
        $this->addSourceSalableQuantityBreakdownMock->expects(self::once())
            ->method('execute')
            ->with(['product1' => $stockEntries, 'product2' => $stockEntries])
            ->willReturnArgument(0);
        $column = new SalableQuantity(
            $this->contextMock,
            $this->uiComponentFactoryMock,
            $this->isSourceItemManagementAllowedForProductTypeMock,
            $this->getSalableQuantityDataBySkuMock,
            $this->getAssignedStockIdsBySkuMock,
            $this->addSourceSalableQuantityBreakdownMock,
            2
        );

        $column->prepareDataSource([
            'data' => [
                'totalRecords' => 2,
                'items' => [
                    ['sku' => 'product1', 'type_id' => 'simple'],
                    ['sku' => 'product2', 'type_id' => 'simple'],
                ],
            ],
        ]);
    }

    public function testRendersTheBreakdownResolvedForTheRow(): void
    {
        $this->isSourceItemManagementAllowedForProductTypeMock->method('execute')->willReturn(true);
        $this->getAssignedStockIdsBySkuMock->method('execute')->willReturn([2]);
        $this->getSalableQuantityDataBySkuMock->method('execute')->willReturn(
            [['stock_id' => 2, 'stock_name' => 'Stock 2', 'qty' => 8, 'manage_stock' => true]]
        );

        $brokenDown = $this->createMock(AddSourceSalableQuantityBreakdown::class);
        $brokenDown->method('execute')->willReturn([
            'product1' => [
                [
                    'stock_id' => 2,
                    'stock_name' => 'Stock 2',
                    'qty' => 8,
                    'manage_stock' => true,
                    'sources' => [['source_code' => 'src_a', 'salable' => 8.0]],
                    'source_reservations_enabled' => true,
                ],
            ],
        ]);
        $column = new SalableQuantity(
            $this->contextMock,
            $this->uiComponentFactoryMock,
            $this->isSourceItemManagementAllowedForProductTypeMock,
            $this->getSalableQuantityDataBySkuMock,
            $this->getAssignedStockIdsBySkuMock,
            $brokenDown,
            2
        );

        $dataSource = $column->prepareDataSource([
            'data' => ['totalRecords' => 1, 'items' => [['sku' => 'product1', 'type_id' => 'simple']]],
        ]);

        self::assertSame(
            'src_a',
            $dataSource['data']['items'][0]['salable_quantity'][0]['sources'][0]['source_code']
        );
    }

    public function testDecodesTheSkuBeforeHandingTheRowToTheBreakdown(): void
    {
        $this->isSourceItemManagementAllowedForProductTypeMock->method('execute')->willReturn(true);
        $this->getAssignedStockIdsBySkuMock->method('execute')->willReturn([2]);
        $this->getSalableQuantityDataBySkuMock->method('execute')->willReturn([]);

        $brokenDown = $this->createMock(AddSourceSalableQuantityBreakdown::class);
        $brokenDown->expects(self::once())
            ->method('execute')
            ->with(['sku&1' => []])
            ->willReturnArgument(0);
        $column = new SalableQuantity(
            $this->contextMock,
            $this->uiComponentFactoryMock,
            $this->isSourceItemManagementAllowedForProductTypeMock,
            $this->getSalableQuantityDataBySkuMock,
            $this->getAssignedStockIdsBySkuMock,
            $brokenDown,
            2
        );

        $column->prepareDataSource([
            'data' => ['totalRecords' => 1, 'items' => [['sku' => 'sku&amp;1', 'type_id' => 'simple']]],
        ]);
    }
}
