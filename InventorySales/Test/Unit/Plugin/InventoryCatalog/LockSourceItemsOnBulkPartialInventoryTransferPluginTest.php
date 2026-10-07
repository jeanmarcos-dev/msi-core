<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryCatalog;

use Magento\InventoryCatalogApi\Api\BulkPartialInventoryTransferInterface;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use Magento\InventorySales\Plugin\InventoryCatalog\LockSourceItemsOnBulkPartialInventoryTransferPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LockSourceItemsOnBulkPartialInventoryTransferPluginTest extends TestCase
{
    /**
     * @var AcquireStockItemLocks|MockObject
     */
    private $locks;

    /**
     * @var array
     */
    private $calls = [];

    /**
     * @var LockSourceItemsOnBulkPartialInventoryTransferPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->locks = $this->createMock(AcquireStockItemLocks::class);
        $this->locks->method('executeForSources')->willReturnCallback(function (array $skus, array $sources) {
            $this->calls[] = ['lock', $skus, $sources];
        });
        $this->locks->method('releaseAll')->willReturnCallback(function () {
            $this->calls[] = ['release'];
        });
        $this->plugin = new LockSourceItemsOnBulkPartialInventoryTransferPlugin($this->locks);
    }

    public function testHoldsTheSourceLocksOfOriginAndDestinationAroundTheTransfer(): void
    {
        $this->plugin->aroundExecute(
            $this->createMock(BulkPartialInventoryTransferInterface::class),
            function () {
                $this->calls[] = ['transfer'];
            },
            'slr_a',
            'slr_b',
            $this->items(['SKU-1', 'SKU-2'])
        );

        self::assertSame(
            [['lock', ['SKU-1', 'SKU-2'], ['slr_a', 'slr_b']], ['transfer'], ['release']],
            $this->calls
        );
    }

    public function testReleasesTheLocksWhenTheTransferFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not available');

        try {
            $this->plugin->aroundExecute(
                $this->createMock(BulkPartialInventoryTransferInterface::class),
                function () {
                    throw new \RuntimeException('not available');
                },
                'slr_a',
                'slr_b',
                $this->items(['SKU-1'])
            );
        } finally {
            self::assertSame(['release'], end($this->calls));
        }
    }

    private function items(array $skus): array
    {
        $items = [];
        foreach ($skus as $sku) {
            $item = $this->createMock(PartialInventoryTransferItemInterface::class);
            $item->method('getSku')->willReturn($sku);
            $items[] = $item;
        }
        return $items;
    }
}
