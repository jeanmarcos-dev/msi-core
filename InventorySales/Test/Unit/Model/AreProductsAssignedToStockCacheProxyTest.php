<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model;

use Magento\Inventory\Model\AreProductsAssignedToStockCache;
use Magento\InventorySales\Model\AreProductsAssignedToStockCacheProxy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AreProductsAssignedToStockCacheProxyTest extends TestCase
{
    public static function stockIds(): array
    {
        return [
            'stock 1' => [1],
            'a merchant stock' => [5],
        ];
    }

    #[DataProvider('stockIds')]
    public function testWarmsUpTheAssignmentsOfEveryStock(int $stockId): void
    {
        $cache = $this->createMock(AreProductsAssignedToStockCache::class);
        $cache->expects($this->once())->method('warmup')->with(['sku-a', 'sku-b'], $stockId);

        (new AreProductsAssignedToStockCacheProxy($cache))->warmup(['sku-a', 'sku-b'], $stockId);
    }

    #[DataProvider('stockIds')]
    public function testCleansTheAssignmentsOfEveryStock(int $stockId): void
    {
        $cache = $this->createMock(AreProductsAssignedToStockCache::class);
        $cache->expects($this->once())->method('clean')->with(['sku-a'], $stockId);

        (new AreProductsAssignedToStockCacheProxy($cache))->clean(['sku-a'], $stockId);
    }
}
