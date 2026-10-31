<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventorySales;

use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Plugin\InventorySales\ReindexAfterReconcileReservationsSweepPlugin;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexAfterReconcileReservationsSweepPluginTest extends TestCase
{
    /**
     * @var StockIndexer|MockObject
     */
    private $stockIndexer;

    /**
     * @var ReconcileReservationsSweep|MockObject
     */
    private $sweep;

    /**
     * @var ReindexAfterReconcileReservationsSweepPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->stockIndexer = $this->createMock(StockIndexer::class);
        $this->sweep = $this->createStub(ReconcileReservationsSweep::class);
        $this->plugin = new ReindexAfterReconcileReservationsSweepPlugin($this->stockIndexer);
    }

    public function testReindexesTheStocksTheSweepCompensated(): void
    {
        $result = $this->sweepResult([2, 3]);
        $this->stockIndexer->expects(self::once())->method('executeList')->with([2, 3]);

        self::assertSame($result, $this->plugin->afterExecute($this->sweep, $result, 500));
    }

    public function testDoesNotReindexOnADryRun(): void
    {
        $result = $this->sweepResult([2, 3]);
        $this->stockIndexer->expects(self::never())->method('executeList');

        self::assertSame($result, $this->plugin->afterExecute($this->sweep, $result, 500, true));
    }

    public function testDoesNotReindexWhenNothingWasCompensated(): void
    {
        $result = $this->sweepResult([]);
        $this->stockIndexer->expects(self::never())->method('executeList');

        self::assertSame($result, $this->plugin->afterExecute($this->sweep, $result, 500));
    }

    /**
     * @param int[] $stockIds
     * @return array
     */
    private function sweepResult(array $stockIds): array
    {
        return [
            'orders' => 1,
            'abandoned' => 0,
            'abandoned_pending' => 0,
            'mark_effective' => false,
            'compensations' => count($stockIds),
            'stock_ids' => $stockIds,
            'limit_reached' => false,
        ];
    }
}
