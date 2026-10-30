<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Test\Unit\Observer;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\InventoryImportExport\Observer\ReindexAfterStatusImportObserver;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSourceItemsBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReindexAfterStatusImportObserverTest extends TestCase
{
    /**
     * @var ReindexSourceItemsBySkus|MockObject
     */
    private $reindexSourceItemsBySkus;

    /**
     * @var ReindexAfterStatusImportObserver
     */
    private $observer;

    protected function setUp(): void
    {
        $this->reindexSourceItemsBySkus = $this->createMock(ReindexSourceItemsBySkus::class);
        $this->observer = new ReindexAfterStatusImportObserver($this->reindexSourceItemsBySkus);
    }

    public function testItReindexesTheSkusWhoseStatusIsInTheBunch(): void
    {
        $this->reindexSourceItemsBySkus->expects(self::once())
            ->method('execute')
            ->with(['child-1', 'child-2']);

        $this->observer->execute($this->createObserver([
            ['sku' => 'child-1', 'status' => '2'],
            ['sku' => 'child-2', 'status' => '1'],
            ['sku' => 'other', 'name' => 'No status here'],
        ]));
    }

    public function testItIgnoresRowsWithAnEmptyStatusCell(): void
    {
        $this->reindexSourceItemsBySkus->expects(self::once())
            ->method('execute')
            ->with(['child-1']);

        $this->observer->execute($this->createObserver([
            ['sku' => 'child-1', 'status' => '1'],
            ['sku' => 'child-2', 'status' => ''],
            ['sku' => 'child-3', 'status' => null],
        ]));
    }

    public function testItReindexesASkuOnceWhenSeveralStoreViewsCarryItsStatus(): void
    {
        $this->reindexSourceItemsBySkus->expects(self::once())
            ->method('execute')
            ->with(['child-1']);

        $this->observer->execute($this->createObserver([
            ['sku' => 'child-1', 'store_view_code' => 'default', 'status' => '1'],
            ['sku' => 'child-1', 'store_view_code' => 'second', 'status' => '2'],
        ]));
    }

    public function testItIgnoresRowsWithoutSku(): void
    {
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $this->observer->execute($this->createObserver([
            ['sku' => '', 'status' => '1'],
            ['status' => '1'],
        ]));
    }

    public function testItDoesNotReindexWhenNoRowCarriesAStatus(): void
    {
        $this->reindexSourceItemsBySkus->expects(self::never())->method('execute');

        $this->observer->execute($this->createObserver([['sku' => 'child-1', 'price' => '10']]));
    }

    private function createObserver(array $bunch): Observer
    {
        return new Observer(['event' => new Event(['bunch' => $bunch])]);
    }
}
