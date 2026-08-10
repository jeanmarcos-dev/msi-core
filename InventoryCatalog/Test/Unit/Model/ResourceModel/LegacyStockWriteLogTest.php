<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryCatalog\Model\ResourceModel\LegacyStockWriteLog;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LegacyStockWriteLogTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var LegacyStockWriteLog
     */
    private $log;

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->log = new LegacyStockWriteLog($resourceConnection);
    }

    public function testGetDetectionsReturnsTheRecordedRows(): void
    {
        $rows = [['table_name' => 'cataloginventory_stock_item', 'operation' => 'update', 'write_count' => '7']];
        $this->connection->method('fetchAll')->willReturn($rows);

        self::assertSame($rows, $this->log->getDetections());
    }

    public function testGetTotalWritesIsZeroWhenNothingWasDetected(): void
    {
        $this->connection->method('fetchOne')->willReturn(null);

        self::assertSame(0, $this->log->getTotalWrites());
    }

    public function testGetTotalWritesCastsTheAggregate(): void
    {
        $this->connection->method('fetchOne')->willReturn('42');

        self::assertSame(42, $this->log->getTotalWrites());
    }

    public function testClearEmptiesTheLogTable(): void
    {
        $this->connection->expects(self::once())->method('delete')->with('inventory_legacy_stock_write');

        $this->log->clear();
    }
}
