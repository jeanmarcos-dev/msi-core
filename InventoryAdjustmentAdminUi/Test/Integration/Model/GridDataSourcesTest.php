<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Test\Integration\Model;

use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea adminhtml
 */
class GridDataSourcesTest extends TestCase
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    protected function setUp(): void
    {
        $this->collectionFactory = Bootstrap::getObjectManager()->get(CollectionFactory::class);
    }

    public function testTheAdjustmentListingKeepsItsDataSource(): void
    {
        self::assertInstanceOf(
            SearchResult::class,
            $this->collectionFactory->getReport('inventory_adjustment_listing_data_source')
        );
    }

    public function testTheProductAdjustmentListingKeepsItsDataSource(): void
    {
        self::assertInstanceOf(
            SearchResult::class,
            $this->collectionFactory->getReport('inventory_adjustment_product_listing_data_source')
        );
    }

    public function testTheSalesOrderGridKeepsItsDataSource(): void
    {
        self::assertNotNull($this->collectionFactory->getReport('sales_order_grid_data_source'));
    }
}
