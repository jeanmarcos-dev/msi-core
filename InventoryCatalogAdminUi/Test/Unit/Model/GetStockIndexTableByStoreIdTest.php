<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Test\Unit\Model;

use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryCatalogAdminUi\Model\GetStockIndexTableByStoreId;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class GetStockIndexTableByStoreIdTest extends TestCase
{
    public function testItResolvesTheIndexTableOfTheStockAssignedToTheStoreWebsite(): void
    {
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(7);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->expects(self::once())->method('getStore')->with(3)->willReturn($store);

        $stock = $this->createMock(StockInterface::class);
        $stock->method('getStockId')->willReturn(12);

        $stockByWebsiteIdResolver = $this->createMock(StockByWebsiteIdResolverInterface::class);
        $stockByWebsiteIdResolver->expects(self::once())->method('execute')->with(7)->willReturn($stock);

        $tableNameResolver = $this->createMock(StockIndexTableNameResolverInterface::class);
        $tableNameResolver->expects(self::once())->method('execute')->with(12)
            ->willReturn('inventory_stock_12');

        $model = new GetStockIndexTableByStoreId($storeManager, $stockByWebsiteIdResolver, $tableNameResolver);

        self::assertSame('inventory_stock_12', $model->execute(3));
    }
}
