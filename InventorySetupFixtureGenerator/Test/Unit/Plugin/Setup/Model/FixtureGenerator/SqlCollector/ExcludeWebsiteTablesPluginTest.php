<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySetupFixtureGenerator\Test\Unit\Plugin\Setup\Model\FixtureGenerator\SqlCollector;

use Magento\InventorySetupFixtureGenerator\Plugin\Setup\Model\FixtureGenerator\SqlCollector\ExcludeWebsiteTablesPlugin;
use Magento\Setup\Model\FixtureGenerator\SqlCollector;
use PHPUnit\Framework\TestCase;

class ExcludeWebsiteTablesPluginTest extends TestCase
{
    public function testDropsTheWebsiteDateTableFromTheCollectedSql(): void
    {
        $collected = [
            [[['website_id' => 1]], 'catalog_product_index_website'],
            [[['entity_id' => 7]], 'catalog_product_entity'],
        ];

        $result = (new ExcludeWebsiteTablesPlugin())->afterGetSql($this->createStub(SqlCollector::class), $collected);

        self::assertSame([[[['entity_id' => 7]], 'catalog_product_entity']], $result);
    }

    public function testKeepsTheCollectedSqlOrder(): void
    {
        $collected = [
            [[['sku' => 'a']], 'inventory_stock_1'],
            [[['sku' => 'a']], 'inventory_source_item'],
        ];

        $result = (new ExcludeWebsiteTablesPlugin())->afterGetSql($this->createStub(SqlCollector::class), $collected);

        self::assertSame($collected, $result);
    }

    public function testReturnsNothingWhenNothingWasCollected(): void
    {
        $result = (new ExcludeWebsiteTablesPlugin())->afterGetSql($this->createStub(SqlCollector::class), []);

        self::assertSame([], $result);
    }
}
