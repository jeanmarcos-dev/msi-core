<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Plugin\Catalog\Model\ResourceModel\Product\Collection;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\InventoryCatalog\Model\GetStockIndexTableByStoreId;
use Magento\InventoryCatalog\Plugin\Catalog\Model\ResourceModel\Product\Collection\RedirectLegacyStockItemJoinPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RedirectLegacyStockItemJoinPluginTest extends TestCase
{
    /**
     * @var Collection|MockObject
     */
    private $collection;

    /**
     * @var RedirectLegacyStockItemJoinPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getStoreId'])
            ->getMock();
        $this->collection->method('getStoreId')->willReturn(0);

        $tableResolver = $this->createMock(GetStockIndexTableByStoreId::class);
        $tableResolver->method('execute')->with(0)->willReturn('inventory_stock_1');

        $this->plugin = new RedirectLegacyStockItemJoinPlugin($tableResolver);
    }

    /**
     * @return array[]
     */
    public static function redirectedFieldsDataProvider(): array
    {
        return [
            'quantity' => ['qty', 'quantity'],
            'salability' => ['is_in_stock', 'is_salable'],
        ];
    }

    /**
     * @dataProvider redirectedFieldsDataProvider
     * @param string $legacyField
     * @param string $indexField
     */
    public function testItRedirectsTheJoinToTheIndexTable(string $legacyField, string $indexField): void
    {
        $arguments = $this->plugin->beforeJoinField(
            $this->collection,
            'alias',
            'cataloginventory_stock_item',
            $legacyField,
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

        self::assertSame(
            ['alias', 'inventory_stock_1', $indexField, 'sku=sku', null, 'left'],
            $arguments
        );
    }

    public function testItLeavesJoinsOnOtherTablesAlone(): void
    {
        self::assertNull(
            $this->plugin->beforeJoinField(
                $this->collection,
                'alias',
                'catalog_product_website',
                'website_id',
                'product_id=entity_id'
            )
        );
    }

    /**
     * Columns such as manage_stock or backorders live in the configuration, not in the index.
     */
    public function testItLeavesLegacyColumnsWithoutAnIndexCounterpartAlone(): void
    {
        self::assertNull(
            $this->plugin->beforeJoinField(
                $this->collection,
                'alias',
                'cataloginventory_stock_item',
                'manage_stock',
                'product_id=entity_id'
            )
        );
    }
}
