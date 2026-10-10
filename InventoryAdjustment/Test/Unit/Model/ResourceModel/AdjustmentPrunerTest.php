<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentPruner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdjustmentPrunerTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var array
     */
    private array $conditions = [];

    /**
     * @var array
     */
    private array $limits = [];

    protected function setUp(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('order')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (string $condition, $value = null) use ($select) {
            $this->conditions[] = [$condition, $value];
            return $select;
        });
        $select->method('limit')->willReturnCallback(function (int $count) use ($select) {
            $this->limits[] = $count;
            return $select;
        });
        $select->method('__toString')->willReturn('LATER ROW');

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($select);
    }

    public function testDeletesBatchesUntilOneComesBackShort(): void
    {
        $this->connection->expects(self::exactly(3))
            ->method('fetchCol')
            ->willReturnOnConsecutiveCalls(['1', '2'], ['3', '4'], ['5']);
        $deleted = [];
        $this->connection->expects(self::exactly(3))
            ->method('delete')
            ->willReturnCallback(function (string $table, array $where) use (&$deleted) {
                self::assertSame('prefix_adjustment', $table);
                $deleted[] = $where['adjustment_id IN (?)'];
                return count($where['adjustment_id IN (?)']);
            });

        self::assertSame(5, $this->pruner()->prune('2026-07-12 02:30:00', 2));
        self::assertSame([['1', '2'], ['3', '4'], ['5']], $deleted);
        self::assertSame([2, 2, 2], $this->limits);
    }

    public function testRunsNoDeleteWhenNothingExpired(): void
    {
        $this->connection->expects(self::once())->method('fetchCol')->willReturn([]);
        $this->connection->expects(self::never())->method('delete');

        self::assertSame(0, $this->pruner()->prune('2026-07-12 02:30:00', 2));
    }

    public function testAFullLastBatchEndsWithAnEmptySelection(): void
    {
        $this->connection->expects(self::exactly(2))
            ->method('fetchCol')
            ->willReturnOnConsecutiveCalls(['1', '2'], []);
        $this->connection->expects(self::once())->method('delete')->willReturn(2);

        self::assertSame(2, $this->pruner()->prune('2026-07-12 02:30:00', 2));
    }

    public function testSelectsOnlyExpiredRowsThatHaveALaterRowOfTheSameItemAndState(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);

        $this->pruner()->prune('2026-07-12 02:30:00', 2);

        self::assertContains(['adjustment.created_at < ?', '2026-07-12 02:30:00'], $this->conditions);
        self::assertContains(['later.source_code = adjustment.source_code', null], $this->conditions);
        self::assertContains(['later.sku = adjustment.sku', null], $this->conditions);
        self::assertContains(['later.state = adjustment.state', null], $this->conditions);
        self::assertContains(['later.adjustment_id > adjustment.adjustment_id', null], $this->conditions);
        self::assertContains(['EXISTS (LATER ROW)', null], $this->conditions);
    }

    private function pruner(): AdjustmentPruner
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->with('inventory_source_item_adjustment')->willReturn('prefix_adjustment');

        return new AdjustmentPruner($resource);
    }
}
