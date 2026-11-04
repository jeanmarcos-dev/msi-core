<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Test\Unit\Plugin\InventoryIndexer\Indexer\Stock\Strategy\Sync;

use Magento\InventoryCache\Model\FlushCacheByProductIds;
use Magento\InventoryCache\Model\ResourceModel\GetProductSalabilityByStockIds;
use Magento\InventoryCache\Plugin\InventoryIndexer\Indexer\Stock\Strategy\Sync\CacheFlush;
use Magento\InventoryIndexer\Indexer\Stock\Strategy\Sync;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CacheFlushTest extends TestCase
{
    public static function reindexOutcomes(): array
    {
        return [
            'a product flips while it stays indexed' => [
                [2 => [10 => 1, 11 => 1, 12 => 0]],
                [2 => [10 => 0, 11 => 1, 12 => 1]],
                [10, 12],
            ],
            'products leave and enter the index' => [
                [2 => [10 => 1, 11 => 0]],
                [2 => [11 => 0, 13 => 1]],
                [10, 13],
            ],
            'each stock is compared on its own' => [
                [2 => [10 => 1], 3 => [10 => 0, 11 => 1]],
                [2 => [10 => 1], 3 => [10 => 1, 11 => 1]],
                [10],
            ],
            'a product changing on two stocks is cleaned once' => [
                [2 => [10 => 1], 3 => [10 => 1]],
                [2 => [10 => 0], 3 => [10 => 0]],
                [10],
            ],
        ];
    }

    #[DataProvider('reindexOutcomes')]
    public function testCleansTheProductsWhoseIndexedSalabilityChanged(
        array $before,
        array $after,
        array $expectedProductIds
    ): void {
        $flushed = new \stdClass();
        $flushed->ids = null;
        $flushCacheByProductIds = $this->createMock(FlushCacheByProductIds::class);
        $flushCacheByProductIds->expects($this->once())->method('execute')->willReturnCallback(
            function (array $productIds) use ($flushed): void {
                sort($productIds);
                $flushed->ids = $productIds;
            }
        );

        $this->aroundExecuteList($flushCacheByProductIds, $before, $after, array_keys($before));

        $this->assertSame($expectedProductIds, $flushed->ids);
    }

    public function testCleansNothingWhenNoSalabilityChanged(): void
    {
        $flushCacheByProductIds = $this->createMock(FlushCacheByProductIds::class);
        $flushCacheByProductIds->expects($this->never())->method('execute');
        $salability = [2 => [10 => 1, 11 => 0]];

        $this->aroundExecuteList($flushCacheByProductIds, $salability, $salability, [2]);
    }

    public function testReadsTheStocksOnEachSideOfTheReindex(): void
    {
        $calls = new \stdClass();
        $calls->log = [];
        $getSalability = $this->createMock(GetProductSalabilityByStockIds::class);
        $getSalability->method('execute')->willReturnCallback(
            function (array $stockIds) use ($calls): array {
                $calls->log[] = ['read', $stockIds];
                return [];
            }
        );
        $plugin = new CacheFlush($this->createMock(FlushCacheByProductIds::class), $getSalability);

        $plugin->aroundExecuteList(
            $this->createMock(Sync::class),
            function (array $stockIds) use ($calls): void {
                $calls->log[] = ['reindex', $stockIds];
            },
            [2, 3]
        );

        $this->assertSame([['read', [2, 3]], ['reindex', [2, 3]], ['read', [2, 3]]], $calls->log);
    }

    private function aroundExecuteList(
        FlushCacheByProductIds $flushCacheByProductIds,
        array $before,
        array $after,
        array $stockIds
    ): void {
        $getSalability = $this->createMock(GetProductSalabilityByStockIds::class);
        $getSalability->method('execute')->with($stockIds)->willReturnOnConsecutiveCalls($before, $after);

        (new CacheFlush($flushCacheByProductIds, $getSalability))->aroundExecuteList(
            $this->createMock(Sync::class),
            fn (array $ids) => null,
            $stockIds
        );
    }
}
