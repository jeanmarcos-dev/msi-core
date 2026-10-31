<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Model\StockReindexBatch;
use Magento\InventoryIndexer\Plugin\InventoryApi\ReindexAfterStockSavePlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexAfterStockSavePluginTest extends TestCase
{
    /**
     * @var StockReindexBatch
     */
    private StockReindexBatch $batch;

    /**
     * @var StockIndexer|MockObject
     */
    private $stockIndexer;

    /**
     * @var ReindexAfterStockSavePlugin
     */
    private ReindexAfterStockSavePlugin $plugin;

    protected function setUp(): void
    {
        $this->batch = new StockReindexBatch();
        $this->stockIndexer = $this->createMock(StockIndexer::class);
        $this->plugin = new ReindexAfterStockSavePlugin($this->batch, $this->stockIndexer);
    }

    public function testStocksCollectedDuringTheSaveAreReindexedOnceAfterIt(): void
    {
        $saved = false;
        $this->stockIndexer->expects(self::once())->method('executeList')->with([2, 1])
            ->willReturnCallback(function () use (&$saved) {
                self::assertTrue($saved);
            });

        $stockId = $this->save(function () use (&$saved) {
            self::assertTrue($this->batch->isOpen());
            $this->batch->add([2]);
            $this->batch->add([1]);
            $saved = true;

            return 2;
        });

        self::assertSame(2, $stockId);
        self::assertFalse($this->batch->isOpen());
    }

    public function testNothingIsReindexedWhenNoStockWasCollected(): void
    {
        $this->stockIndexer->expects(self::never())->method('executeList');

        self::assertSame(5, $this->save(fn () => 5));
    }

    public function testFailedSaveReindexesNothingAndClosesTheBatch(): void
    {
        $this->stockIndexer->expects(self::never())->method('executeList');

        $this->saveSwallowingFailure(function () {
            $this->batch->add([2]);
            throw new \RuntimeException('save failed');
        });

        self::assertFalse($this->batch->isOpen());
        $this->batch->open();
        self::assertSame([], $this->batch->close());
    }

    public function testFailureOfTheSaveReachesTheCaller(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('save failed');

        $this->save(function () {
            throw new \RuntimeException('save failed');
        });
    }

    /**
     * Run a save that fails and ignore the failure.
     *
     * @param callable $proceed
     * @return void
     */
    private function saveSwallowingFailure(callable $proceed): void
    {
        try {
            $this->save($proceed);
        } catch (\RuntimeException $exception) {
            return;
        }
    }

    /**
     * Run the plugin the way the interceptor does.
     *
     * @param callable $proceed
     * @return int
     */
    private function save(callable $proceed): int
    {
        return $this->plugin->aroundSave(
            $this->createMock(StockRepositoryInterface::class),
            $proceed,
            $this->createMock(StockInterface::class)
        );
    }
}
