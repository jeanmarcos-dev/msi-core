<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\InventoryCatalog\Model\SortingAdjustment;
use PHPUnit\Framework\TestCase;

class SortingAdjustmentTest extends TestCase
{
    /**
     * @var SortingAdjustment
     */
    private $model;

    protected function setUp(): void
    {
        $this->model = new SortingAdjustment();
    }

    public function testItRunsTheInventoryIndexerBeforeThePriceIndexer(): void
    {
        $adjusted = $this->model->adjust([
            'catalog_product_price' => ['indexer_id' => 'catalog_product_price'],
            'inventory' => ['indexer_id' => 'inventory'],
            'catalogsearch_fulltext' => ['indexer_id' => 'catalogsearch_fulltext'],
        ]);

        self::assertSame(['inventory', 'catalog_product_price', 'catalogsearch_fulltext'], array_keys($adjusted));
    }

    public function testItLeavesAnAlreadyOrderedListAlone(): void
    {
        $list = [
            'inventory' => ['indexer_id' => 'inventory'],
            'catalog_product_price' => ['indexer_id' => 'catalog_product_price'],
        ];

        self::assertSame($list, $this->model->adjust($list));
    }

    /**
     * The legacy stock indexer used to be hoisted to the front; it is inert now, so its position is
     * irrelevant and the list must come back untouched.
     */
    public function testItNoLongerHoistsTheLegacyStockIndexer(): void
    {
        $list = [
            'catalog_category_product' => ['indexer_id' => 'catalog_category_product'],
            'cataloginventory_stock' => ['indexer_id' => 'cataloginventory_stock'],
        ];

        self::assertSame($list, $this->model->adjust($list));
    }

    public function testItLeavesTheListAloneWhenTheInventoryIndexerIsAbsent(): void
    {
        $list = [
            'catalog_product_price' => ['indexer_id' => 'catalog_product_price'],
            'cataloginventory_stock' => ['indexer_id' => 'cataloginventory_stock'],
        ];

        self::assertSame($list, $this->model->adjust($list));
    }
}
