<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsDeleteInterface;
use Magento\InventoryStockVisualizer\Model\Cache\DispatchPurge;
use Magento\InventoryStockVisualizer\Model\Cache\GetEnabledStoreIds;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveSkusToPurge;
use Magento\InventoryStockVisualizer\Model\Cache\SnapshotSourceItemQty;
use Magento\InventoryStockVisualizer\Model\Cache\SourceItemDeltaBuilder;
use Magento\InventoryStockVisualizer\Plugin\InventoryApi\PurgeOnSourceItemsDelete;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PurgeOnSourceItemsDeleteTest extends TestCase
{
    /**
     * @var GetEnabledStoreIds|MockObject
     */
    private $enabledStores;

    /**
     * @var SnapshotSourceItemQty|MockObject
     */
    private $snapshot;

    /**
     * @var SourceItemDeltaBuilder|MockObject
     */
    private $deltaBuilder;

    /**
     * @var ResolveSkusToPurge|MockObject
     */
    private $resolveSkusToPurge;

    /**
     * @var DispatchPurge|MockObject
     */
    private $dispatchPurge;

    /**
     * @var SourceItemsDeleteInterface|MockObject
     */
    private $subject;

    /**
     * @var PurgeOnSourceItemsDelete
     */
    private $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enabledStores = $this->createMock(GetEnabledStoreIds::class);
        $this->snapshot = $this->createMock(SnapshotSourceItemQty::class);
        $this->deltaBuilder = $this->createMock(SourceItemDeltaBuilder::class);
        $this->resolveSkusToPurge = $this->createMock(ResolveSkusToPurge::class);
        $this->dispatchPurge = $this->createMock(DispatchPurge::class);
        $this->subject = $this->createMock(SourceItemsDeleteInterface::class);

        $this->plugin = new PurgeOnSourceItemsDelete(
            $this->enabledStores,
            $this->snapshot,
            $this->deltaBuilder,
            $this->resolveSkusToPurge,
            $this->dispatchPurge,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testSnapshotsBeforeAndDispatchesAfter(): void
    {
        $items = [$this->createMock(SourceItemInterface::class)];
        $snapshot = ['SKU-1|slr_a' => 5.0];
        $deltas = [10 => ['SKU-1' => ['total' => -2.0, 'bySource' => ['slr_a' => -2.0]]]];

        $this->enabledStores->method('execute')->willReturn([1]);
        $this->snapshot->expects($this->once())->method('execute')->with($items)->willReturn($snapshot);
        $this->deltaBuilder->expects($this->once())->method('build')
            ->with($items, $snapshot, true)
            ->willReturn($deltas);
        $this->resolveSkusToPurge->expects($this->once())->method('execute')->with($deltas)->willReturn(['SKU-1']);
        $this->dispatchPurge->expects($this->once())->method('execute')->with(['SKU-1']);

        $this->plugin->beforeExecute($this->subject, $items);
        $this->assertNull($this->plugin->afterExecute($this->subject, null, $items));
    }

    public function testAPanelEnabledOnlyOnAStoreViewStillPurges(): void
    {
        $items = [$this->createMock(SourceItemInterface::class)];
        $deltas = [10 => ['SKU-1' => ['total' => -2.0, 'bySource' => ['slr_a' => -2.0]]]];
        $this->enabledStores->method('execute')->willReturn([2]);
        $this->snapshot->expects($this->once())->method('execute')->willReturn(['SKU-1|slr_a' => 5.0]);
        $this->deltaBuilder->method('build')->willReturn($deltas);
        $this->resolveSkusToPurge->method('execute')->willReturn(['SKU-1']);
        $this->dispatchPurge->expects($this->once())->method('execute')->with(['SKU-1']);

        $this->plugin->beforeExecute($this->subject, $items);
        $this->plugin->afterExecute($this->subject, null, $items);
    }

    public function testDisabledDoesNothing(): void
    {
        $items = [$this->createMock(SourceItemInterface::class)];
        $this->enabledStores->method('execute')->willReturn([]);
        $this->snapshot->expects($this->never())->method('execute');
        $this->deltaBuilder->expects($this->never())->method('build');
        $this->dispatchPurge->expects($this->never())->method('execute');

        $this->plugin->beforeExecute($this->subject, $items);
        $this->plugin->afterExecute($this->subject, null, $items);
    }

    public function testNoDeltasDoesNotDispatch(): void
    {
        $items = [$this->createMock(SourceItemInterface::class)];
        $this->enabledStores->method('execute')->willReturn([1]);
        $this->snapshot->method('execute')->willReturn([]);
        $this->deltaBuilder->method('build')->willReturn([]);
        $this->resolveSkusToPurge->expects($this->never())->method('execute');
        $this->dispatchPurge->expects($this->never())->method('execute');

        $this->plugin->beforeExecute($this->subject, $items);
        $this->plugin->afterExecute($this->subject, null, $items);
    }
}
