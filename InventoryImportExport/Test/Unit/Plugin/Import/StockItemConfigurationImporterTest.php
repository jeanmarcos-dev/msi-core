<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Test\Unit\Plugin\Import;

use Magento\CatalogImportExport\Model\StockItemProcessorInterface;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryImportExport\Plugin\Import\SourceItemImporter;
use Magento\InventoryImportExport\Plugin\Import\StockItemConfigurationImporter;
use PHPUnit\Framework\TestCase;

class StockItemConfigurationImporterTest extends TestCase
{
    public function testItMirrorsTheConfigurationColumnsTheImportedRowCarries(): void
    {
        $resource = $this->createMock(StockItemConfigurationResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->with([
                ['sku' => 'SKU-1', 'min_qty' => 2, 'use_config_min_qty' => 0],
                ['sku' => 'SKU-2', 'backorders' => 1, 'use_config_backorders' => 0],
            ]);

        (new StockItemConfigurationImporter($resource))->afterProcess(
            $this->createStub(StockItemProcessorInterface::class),
            null,
            [
                'SKU-1' => ['min_qty' => 2, 'use_config_min_qty' => 0, 'qty' => 5, 'product_id' => 42],
                'SKU-2' => ['backorders' => 1, 'use_config_backorders' => 0],
            ],
            [
                'SKU-1' => ['sku' => 'SKU-1', 'min_qty' => 2, 'qty' => 5],
                'SKU-2' => ['sku' => 'SKU-2', 'backorders' => 1],
            ]
        );
    }

    public function testItKeepsTheSkuOfARowThatCarriesNoConfigurationColumn(): void
    {
        $resource = $this->createMock(StockItemConfigurationResource::class);
        $resource->expects(self::once())
            ->method('save')
            ->with([['sku' => 'SKU-1']]);

        (new StockItemConfigurationImporter($resource))->afterProcess(
            $this->createStub(StockItemProcessorInterface::class),
            null,
            ['SKU-1' => ['qty' => 5]],
            ['SKU-1' => ['sku' => 'SKU-1', 'qty' => 5]]
        );
    }

    public function testItLeavesAStoredConfigurationAloneWhenTheImportRowCarriesNoStockColumn(): void
    {
        $resource = $this->createMock(StockItemConfigurationResource::class);
        $resource->method('get')->willReturn(['SKU-1' => ['sku' => 'SKU-1', 'is_in_stock' => 1]]);
        $resource->expects(self::once())->method('save')->with([]);

        (new StockItemConfigurationImporter($resource))->afterProcess(
            $this->createStub(StockItemProcessorInterface::class),
            null,
            ['SKU-1' => ['is_in_stock' => 0, 'min_qty' => 0, 'qty' => 100]],
            ['SKU-1' => ['sku' => 'SKU-1', 'name' => 'Updated name']]
        );
    }

    public function testItWritesAStoredConfigurationWhenTheImportRowCarriesAStockColumn(): void
    {
        $resource = $this->createMock(StockItemConfigurationResource::class);
        $resource->method('get')->willReturn(['SKU-1' => ['sku' => 'SKU-1', 'is_in_stock' => 1]]);
        $resource->expects(self::once())
            ->method('save')
            ->with([['sku' => 'SKU-1', 'min_qty' => 2]]);

        (new StockItemConfigurationImporter($resource))->afterProcess(
            $this->createStub(StockItemProcessorInterface::class),
            null,
            ['SKU-1' => ['min_qty' => 2]],
            ['SKU-1' => ['sku' => 'SKU-1', 'out_of_stock_qty' => 2]]
        );
    }

    public function testItWritesNoConfigurationForANewProductWhoseRowCarriesNoStockColumn(): void
    {
        $resource = $this->createMock(StockItemConfigurationResource::class);
        $resource->method('get')->willReturn([]);
        $resource->expects(self::once())->method('save')->with([]);

        (new StockItemConfigurationImporter($resource))->afterProcess(
            $this->createStub(StockItemProcessorInterface::class),
            null,
            ['grouped1' => ['is_in_stock' => 0, 'qty' => 0]],
            ['grouped1' => ['sku' => 'grouped1', 'product_type' => 'grouped', 'name' => 'Grouped Product 1']]
        );
    }

    public function testItLeavesAStoredConfigurationAloneWhenTheStockColumnIsEmpty(): void
    {
        $resource = $this->createMock(StockItemConfigurationResource::class);
        $resource->method('get')->willReturn(['SKU-1' => ['sku' => 'SKU-1', 'min_qty' => 5]]);
        $resource->expects(self::once())->method('save')->with([]);

        (new StockItemConfigurationImporter($resource))->afterProcess(
            $this->createStub(StockItemProcessorInterface::class),
            null,
            ['SKU-1' => ['min_qty' => '']],
            ['SKU-1' => ['sku' => 'SKU-1', 'min_qty' => '']]
        );
    }

    public function testItIsWiredToRunBeforeTheSourceItemImporterThatReindexes(): void
    {
        $sortOrders = $this->getPluginSortOrdersOfStockItemProcessor();

        self::assertArrayHasKey(StockItemConfigurationImporter::class, $sortOrders);
        self::assertArrayHasKey(SourceItemImporter::class, $sortOrders);
        self::assertLessThan(
            $sortOrders[SourceItemImporter::class],
            $sortOrders[StockItemConfigurationImporter::class]
        );
    }

    private function getPluginSortOrdersOfStockItemProcessor(): array
    {
        $di = simplexml_load_file(__DIR__ . '/../../../../etc/di.xml');
        self::assertNotFalse($di, 'The module di.xml could not be read.');

        $sortOrders = [];
        $xpath = sprintf('/config/type[@name="%s"]/plugin', StockItemProcessorInterface::class);
        foreach ($di->xpath($xpath) ?: [] as $plugin) {
            $sortOrders[(string)$plugin['type']] = (int)$plugin['sortOrder'];
        }

        return $sortOrders;
    }
}
