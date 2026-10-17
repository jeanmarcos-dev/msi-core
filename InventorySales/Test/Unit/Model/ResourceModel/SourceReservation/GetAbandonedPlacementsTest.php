<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetAbandonedPlacements;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetAbandonedPlacementsTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var array<int,array<int,array{0:string,1:array}>>
     */
    private array $calls = [];

    /**
     * @var GetAbandonedPlacements
     */
    private $getAbandonedPlacements;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturnCallback(fn() => $this->recordingSelect(count($this->calls)));
        $this->connection->method('quoteInto')->willReturnCallback(
            fn(string $text, $value) => str_replace('?', "'" . $value . "'", $text)
        );

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->getAbandonedPlacements = new GetAbandonedPlacements($resourceConnection);
    }

    public function testMapsRowsOldestFirst(): void
    {
        $this->connection->method('fetchAll')->willReturn([
            ['increment_id' => 7, 'last_id' => '100'],
            ['increment_id' => '000000008', 'last_id' => '200'],
        ]);

        self::assertSame(
            [['increment_id' => '7', 'last_id' => 100], ['increment_id' => '000000008', 'last_id' => 200]],
            $this->getAbandonedPlacements->execute()
        );
    }

    public function testGroupsPerLedgerGroupThenPerIncrementIdAndExcludesSavedOrders(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);
        $this->getAbandonedPlacements->execute();

        [$groups, $placements, $order, $outer] = array_values($this->calls);

        self::assertSame('inventory_reservation', $groups[0][1][0]);
        self::assertStringContainsString("'order_placed'", (string)$groups[0][1][1]['placed']);
        self::assertStringEndsWith(' IS NOT NULL', $groups[1][1][0]);
        self::assertSame(['increment_id', 'stock_id', 'sku', 'source_code'], $groups[3][1][0]);

        self::assertSame(['placement' => 'SELECT#0'], $this->tableArgument($placements[0]));
        self::assertSame(['having', ['SUM(placement.placed) > 0']], $this->leading($placements[2], 1));
        self::assertSame(['having', ['MIN(placement.balance) < ?', -0.000001]], $this->leading($placements[3], 2));

        self::assertSame(['so' => 'sales_order'], $this->tableArgument($order[0]));
        self::assertSame(['where', ['so.increment_id = abandoned.increment_id']], $this->leading($order[1], 1));

        self::assertSame(['abandoned' => 'SELECT#1'], $this->tableArgument($outer[0]));
        self::assertSame(['where', ['NOT EXISTS (SELECT#2)']], $this->leading($outer[1], 1));
        self::assertSame(['order', ['abandoned.last_id ASC']], $this->leading($outer[2], 1));
    }

    /**
     * @param int $index
     * @return Select
     */
    private function recordingSelect(int $index): Select
    {
        $this->calls[$index] = [];
        $select = $this->createMock(Select::class);
        foreach (['from', 'where', 'group', 'having', 'order'] as $method) {
            $select->method($method)->willReturnCallback(
                function (...$arguments) use ($method, $select, $index) {
                    $this->calls[$index][] = [$method, $arguments];
                    return $select;
                }
            );
        }
        $select->method('__toString')->willReturn('SELECT#' . $index);

        return $select;
    }

    /**
     * @param array{0:string,1:array} $call
     * @param int $count
     * @return array
     */
    private function leading(array $call, int $count): array
    {
        return [$call[0], array_slice($call[1], 0, $count)];
    }

    /**
     * @param array{0:string,1:array} $call
     * @return array
     */
    private function tableArgument(array $call): array
    {
        return array_map(fn($table) => (string)$table, $call[1][0]);
    }
}
