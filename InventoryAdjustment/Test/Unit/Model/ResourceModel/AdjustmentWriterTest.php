<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentWriter;
use PHPUnit\Framework\TestCase;

class AdjustmentWriterTest extends TestCase
{
    public function testInsertsAllRowsInOneStatement(): void
    {
        $rows = [['sku' => 'A', 'delta' => 1.0], ['sku' => 'B', 'delta' => -2.0]];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('insertMultiple')->with('prefix_adjustment', $rows);

        $this->writer($connection)->write($rows);
    }

    public function testSkipsTheQueryWhenThereIsNothingToWrite(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::never())->method('insertMultiple');

        $this->writer($connection)->write([]);
    }

    private function writer(AdapterInterface $connection): AdjustmentWriter
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->with('inventory_source_item_adjustment')->willReturn('prefix_adjustment');

        return new AdjustmentWriter($resource);
    }
}
