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
use Magento\InventoryAdjustment\Model\ResourceModel\SourceItemSnapshot;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SourceItemSnapshotTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var array
     */
    private array $queries = [];

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturnCallback(fn () => $this->newSelect());
    }

    public function testRunsNoQueryWithoutKeys(): void
    {
        $this->connection->expects(self::never())->method('select');

        self::assertSame([], $this->snapshot()->lock([]));
        self::assertSame([], $this->snapshot()->read([]));
    }

    public function testLooksUpIdsOncePerSourceWithoutRepeatedSkus(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);

        $this->snapshot()->read([
            $this->key('a', 'X'),
            $this->key('b', 'Y'),
            $this->key('a', 'Z'),
            $this->key('a', 'X'),
        ]);

        self::assertSame(
            [
                [['source_code = ?', 'a'], ['sku IN (?)', ['X', 'Z']]],
                [['source_code = ?', 'b'], ['sku IN (?)', ['Y']]],
            ],
            array_column($this->queries, 'where')
        );
    }

    public function testNumericSourceCodesAndSkusReachTheQueryAsStrings(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);

        $this->snapshot()->read([$this->key('10', '20')]);

        self::assertSame(
            [['source_code = ?', '10'], ['sku IN (?)', ['20']]],
            $this->queries[0]['where']
        );
    }

    public function testSplitsTheSkusOfASourceIntoBatchesOf200(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);
        $keys = [];
        for ($i = 1; $i <= 450; $i++) {
            $keys[] = $this->key('default', 'SKU-' . $i);
        }

        $this->snapshot()->read($keys);

        self::assertSame(
            [200, 200, 50],
            array_map(fn (array $query) => count($query['where'][1][1]), $this->queries)
        );
        self::assertSame('SKU-201', $this->queries[1]['where'][1][1][0]);
    }

    public function testLockReadsEveryFoundIdForUpdateInIdOrder(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['8', '3'], ['5']);
        $this->connection->expects(self::once())->method('fetchAll')->willReturn([
            ['source_code' => 'a', 'sku' => 'X', 'quantity' => '4.5000', 'status' => '1'],
            ['source_code' => 'b', 'sku' => 'Y', 'quantity' => '0.0000', 'status' => '0'],
        ]);

        $snapshot = $this->snapshot()->lock([$this->key('a', 'X'), $this->key('a', 'Z'), $this->key('b', 'Y')]);

        self::assertSame(
            ['a' => ['X' => ['quantity' => 4.5, 'status' => 1]], 'b' => ['Y' => ['quantity' => 0.0, 'status' => 0]]],
            $snapshot
        );
        $itemQuery = end($this->queries);
        self::assertSame([['source_item_id IN (?)', ['8', '3', '5']]], $itemQuery['where']);
        self::assertSame('source_item_id', $itemQuery['order']);
        self::assertTrue($itemQuery['forUpdate']);
    }

    public function testReadDoesNotLock(): void
    {
        $this->connection->method('fetchCol')->willReturn(['3']);
        $this->connection->method('fetchAll')->willReturn([]);

        $this->snapshot()->read([$this->key('a', 'X')]);

        self::assertFalse(end($this->queries)['forUpdate']);
    }

    public function testSkipsTheItemQueryWhenNoItemExists(): void
    {
        $this->connection->method('fetchCol')->willReturn([]);
        $this->connection->expects(self::never())->method('fetchAll');

        self::assertSame([], $this->snapshot()->lock([$this->key('a', 'X')]));
    }

    private function newSelect(): Select
    {
        $index = count($this->queries);
        $this->queries[$index] = ['where' => [], 'order' => null, 'forUpdate' => null];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (string $condition, $value = null) use ($select, $index) {
            $this->queries[$index]['where'][] = [$condition, $value];
            return $select;
        });
        $select->method('order')->willReturnCallback(function (string $order) use ($select, $index) {
            $this->queries[$index]['order'] = $order;
            return $select;
        });
        $select->method('forUpdate')->willReturnCallback(function (bool $flag) use ($select, $index) {
            $this->queries[$index]['forUpdate'] = $flag;
            return $select;
        });

        return $select;
    }

    private function key(string $sourceCode, string $sku): array
    {
        return ['source_code' => $sourceCode, 'sku' => $sku];
    }

    private function snapshot(): SourceItemSnapshot
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new SourceItemSnapshot($resource);
    }
}
