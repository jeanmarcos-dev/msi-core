<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Test\Unit\Model\Export\RowCustomizer;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfiguration\Model\GetStockItemsConfigurationInterface;
use Magento\InventoryImportExport\Model\Export\RowCustomizer\StockItemColumns;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use PHPUnit\Framework\TestCase;

class StockItemColumnsTest extends TestCase
{
    private const PRODUCT_ID = 42;
    private const SKU = 'sku-42';

    /**
     * @var StockItemColumns
     */
    private $model;

    protected function setUp(): void
    {
        $getSkusByProductIds = $this->createMock(GetSkusByProductIdsInterface::class);
        $getSkusByProductIds->method('execute')->with([self::PRODUCT_ID])
            ->willReturn([self::PRODUCT_ID => self::SKU]);

        $configuration = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $configuration->setData(
            [
                'min_qty' => 3.0,
                'use_config_min_qty' => 0,
                'backorders' => 1,
                'use_config_max_sale_qty' => 1,
                'use_config_min_sale_qty' => 0,
                'min_sale_qty' => 2.0,
                'use_config_manage_stock' => 1,
                // Deliberately stale: the index is the source of truth for these two.
                'qty' => 999.0,
                'is_in_stock' => 0,
            ]
        );

        $getStockItemsConfiguration = $this->createMock(GetStockItemsConfigurationInterface::class);
        $getStockItemsConfiguration->method('execute')->with([self::SKU])
            ->willReturn([self::SKU => $configuration]);

        $getStockItemsData = $this->createMock(GetStockItemsDataInterface::class);
        $getStockItemsData->method('execute')->with([self::SKU], 7)
            ->willReturn([self::SKU => ['quantity' => '12.5000', 'is_salable' => '1']]);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(7);
        $stockByWebsiteIdResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockByWebsiteIdResolver->method('execute')->with(0)->willReturn($stock);

        $stockConfiguration = $this->createMock(StockConfigurationInterface::class);
        $stockConfiguration->method('getDefaultScopeId')->willReturn(0);
        $stockConfiguration->method('getMaxSaleQty')->willReturn(10000.0);
        $stockConfiguration->method('getMinSaleQty')->willReturn(1.0);
        $stockConfiguration->method('getManageStock')->willReturn(1);

        $this->model = new StockItemColumns(
            $getSkusByProductIds,
            $getStockItemsConfiguration,
            $getStockItemsData,
            $stockByWebsiteIdResolver,
            $stockConfiguration
        );
    }

    public function testItTakesQuantityAndSalabilityFromTheIndex(): void
    {
        $this->model->prepareData(null, [self::PRODUCT_ID]);
        $row = $this->model->addData(['sku' => self::SKU], self::PRODUCT_ID);

        self::assertSame(12.5, $row['qty']);
        self::assertSame(1, $row['is_in_stock']);
    }

    public function testItResolvesTheConfigBackedFieldsLikeTheExporterDoes(): void
    {
        $this->model->prepareData(null, [self::PRODUCT_ID]);
        $row = $this->model->addData([], self::PRODUCT_ID);

        self::assertSame(10000.0, $row['max_sale_qty'], 'use_config_max_sale_qty is on');
        self::assertSame(2.0, $row['min_sale_qty'], 'use_config_min_sale_qty is off');
        self::assertSame(1, $row['manage_stock'], 'use_config_manage_stock is on');
        self::assertSame(3.0, $row['min_qty']);
        self::assertSame(1, $row['backorders']);
    }

    public function testItAddsTheStockColumnsToTheHeaderWithoutDuplicating(): void
    {
        $columns = $this->model->addHeaderColumns(['sku', 'qty']);

        self::assertSame(['sku', 'qty'], array_slice($columns, 0, 2));
        self::assertSame(1, count(array_keys($columns, 'qty', true)));
        self::assertContains('is_in_stock', $columns);
        self::assertContains('use_config_min_qty', $columns);
    }

    public function testItLeavesRowsOfProductsItKnowsNothingAboutUntouched(): void
    {
        $this->model->prepareData(null, [self::PRODUCT_ID]);

        self::assertSame(['sku' => 'other'], $this->model->addData(['sku' => 'other'], 999));
    }
}
