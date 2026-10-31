<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model;

use Magento\InventoryIndexer\Model\StockReindexBatch;
use PHPUnit\Framework\TestCase;

class StockReindexBatchTest extends TestCase
{
    /**
     * @var StockReindexBatch
     */
    private StockReindexBatch $batch;

    protected function setUp(): void
    {
        $this->batch = new StockReindexBatch();
    }

    public function testIsClosedByDefault(): void
    {
        self::assertFalse($this->batch->isOpen());
    }

    public function testIsOpenBetweenOpenAndClose(): void
    {
        $this->batch->open();
        self::assertTrue($this->batch->isOpen());

        $this->batch->close();
        self::assertFalse($this->batch->isOpen());
    }

    public function testCloseReturnsTheCollectedStocksWithoutDuplicates(): void
    {
        $this->batch->open();
        $this->batch->add([2, 1]);
        $this->batch->add([1, 3]);

        self::assertSame([2, 1, 3], $this->batch->close());
    }

    public function testCloseForgetsTheCollectedStocks(): void
    {
        $this->batch->open();
        $this->batch->add([2]);
        $this->batch->close();
        $this->batch->open();

        self::assertSame([], $this->batch->close());
    }

    public function testNestedSavesReturnTheStocksOnlyWhenTheOutermostEnds(): void
    {
        $this->batch->open();
        $this->batch->open();
        $this->batch->add([2]);

        self::assertSame([], $this->batch->close());
        self::assertTrue($this->batch->isOpen());
        self::assertSame([2], $this->batch->close());
    }

    public function testDiscardForgetsTheCollectedStocksWhenTheOutermostSaveFails(): void
    {
        $this->batch->open();
        $this->batch->add([2]);
        $this->batch->discard();
        $this->batch->open();

        self::assertSame([], $this->batch->close());
    }

    public function testClosingWithoutOpeningKeepsTheBatchClosed(): void
    {
        $this->batch->close();
        $this->batch->add([2]);

        self::assertFalse($this->batch->isOpen());
    }
}
