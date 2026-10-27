<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\LockWaitException;
use Magento\Framework\DB\Select;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderBalanceGuard;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderBalanceGuardTest extends TestCase
{
    private const TABLE = 'prefix_inventory_reservation_order_balance';

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var array
     */
    private $calls = [];

    /**
     * @var array
     */
    private $where = [];

    /**
     * @var array
     */
    private $deletes = [];

    /**
     * @var OrderBalanceGuard
     */
    private $guard;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('insertOnDuplicate')
            ->willReturnCallback(function (string $table, array $data, array $fields) {
                $this->calls[] = ['upsert', $table, $data, array_map('strval', $fields)];
                return 1;
            });
        $this->connection->method('select')->willReturnCallback(fn () => $this->select());

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->with('inventory_reservation_order_balance')->willReturn(self::TABLE);

        $this->guard = new OrderBalanceGuard($resource);
    }

    public function testCreatesEachRowFromTheLedgerAndLocksItInKeyOrder(): void
    {
        $this->connection->method('fetchOne')->willReturnOnConsecutiveCalls('-1.0000', '-3.0000');

        $balances = $this->guard->lock([
            'later' => $this->group('000000002', 'SKU-1', 'slr_a', -3.0),
            'earlier' => $this->group('000000001', 'SKU-1', 'slr_b', -1.0),
        ]);

        self::assertSame(
            [
                ['upsert', self::TABLE, [$this->row('000000001', 'SKU-1', 'slr_b', -1.0)], ['balance' => 'balance']],
                ['lock', ['000000001', 5, 'SKU-1', 'slr_b']],
                ['upsert', self::TABLE, [$this->row('000000002', 'SKU-1', 'slr_a', -3.0)], ['balance' => 'balance']],
                ['lock', ['000000002', 5, 'SKU-1', 'slr_a']],
            ],
            $this->calls
        );
        self::assertSame(['earlier' => -1.0, 'later' => -3.0], $balances);
    }

    public function testReturnsTheCommittedBalanceRatherThanTheLedger(): void
    {
        $this->connection->method('fetchOne')->willReturn('0.0000');

        $balances = $this->guard->lock(['group' => $this->group('000000001', 'SKU-1', 'slr_a', -1.0)]);

        self::assertSame(['group' => 0.0], $balances);
    }

    public function testLeavesAGroupUnlockedWhenItsRowStaysLockedAndGoesOn(): void
    {
        $this->connection->method('fetchOne')->willReturnCallback(function () {
            if (count($this->calls) === 2) {
                throw new LockWaitException('Lock wait timeout exceeded');
            }
            return '-2.0000';
        });

        $balances = $this->guard->lock([
            'held' => $this->group('000000001', 'SKU-1', 'slr_a', -1.0),
            'free' => $this->group('000000001', 'SKU-2', 'slr_a', -2.0),
        ]);

        self::assertSame(['held' => null, 'free' => -2.0], $balances);
    }

    public function testLeavesAGroupUnlockedWhenCreatingItsRowTimesOut(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('insertOnDuplicate')->willThrowException(new LockWaitException('Lock wait timeout'));
        $connection->expects(self::never())->method('fetchOne');
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturn(self::TABLE);

        $balances = (new OrderBalanceGuard($resource))->lock(
            ['held' => $this->group('000000001', 'SKU-1', 'slr_a', -1.0)]
        );

        self::assertSame(['held' => null], $balances);
    }

    public function testLocksNothingWithoutGroups(): void
    {
        $this->connection->expects(self::never())->method('insertOnDuplicate');

        self::assertSame([], $this->guard->lock([]));
    }

    public function testAddsTheWrittenQuantitiesOfEachGroupInOneStatement(): void
    {
        $this->guard->add([
            $this->written('000000002', 'SKU-1', 'slr_a', 1.0),
            $this->written('000000001', 'SKU-1', 'slr_a', -2.0),
            $this->written('000000002', 'SKU-1', 'slr_a', 0.5),
        ]);

        self::assertSame(
            [[
                'upsert',
                self::TABLE,
                [$this->row('000000001', 'SKU-1', 'slr_a', -2.0), $this->row('000000002', 'SKU-1', 'slr_a', 1.5)],
                ['balance' => 'balance + VALUES(balance)'],
            ]],
            $this->calls
        );
    }

    public function testAddsNothingWithoutRows(): void
    {
        $this->connection->expects(self::never())->method('insertOnDuplicate');

        $this->guard->add([]);
    }

    public function testDeletesTheSettledRowsByKeyInBatches(): void
    {
        $this->givenSettled(
            [
                [$this->key('000000001', 'SKU-1', 'slr_a'), $this->key('000000002', 'SKU-1', '')],
                [$this->key('000000003', 'SKU-2', 'slr_b')],
            ],
            [2, 1]
        );

        self::assertSame(3, $this->guard->deleteSettled(2));
        self::assertSame(
            [
                [
                    self::TABLE,
                    [
                        'balance = 0',
                        "(object_increment_id = '000000001' AND stock_id = 5 AND sku = 'SKU-1'"
                        . " AND source_code = 'slr_a') OR (object_increment_id = '000000002' AND stock_id = 5"
                        . " AND sku = 'SKU-1' AND source_code = '')",
                    ],
                ],
                [
                    self::TABLE,
                    [
                        'balance = 0',
                        "(object_increment_id = '000000003' AND stock_id = 5 AND sku = 'SKU-2'"
                        . " AND source_code = 'slr_b')",
                    ],
                ],
            ],
            $this->deletes
        );
        self::assertSame(['balance = 0', 'balance = 0'], $this->where);
    }

    public function testStopsWhenABatchDeletesNothing(): void
    {
        $this->givenSettled(
            [[$this->key('000000001', 'SKU-1', 'slr_a'), $this->key('000000002', 'SKU-1', 'slr_a')]],
            [0]
        );

        self::assertSame(0, $this->guard->deleteSettled(2));
        self::assertCount(1, $this->deletes);
    }

    public function testDeletesNothingWithoutSettledRows(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);
        $this->connection->expects(self::never())->method('delete');

        self::assertSame(0, $this->guard->deleteSettled());
    }

    /**
     * @param array<int, array<int, array>> $batches
     * @param int[] $deleted
     */
    private function givenSettled(array $batches, array $deleted): void
    {
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(...$batches);
        $this->connection->method('quoteInto')->willReturnCallback(
            static fn (string $text, $value) => str_replace('?', is_int($value) ? (string)$value : "'$value'", $text)
        );
        $this->connection->method('delete')->willReturnCallback(
            function (string $table, array $where) use (&$deleted) {
                $this->deletes[] = [$table, $where];
                return array_shift($deleted);
            }
        );
    }

    private function key(string $incrementId, string $sku, string $sourceCode): array
    {
        return ['object_increment_id' => $incrementId, 'stock_id' => '5', 'sku' => $sku, 'source_code' => $sourceCode];
    }

    private function select(): Select
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (string $condition, $value = null) use ($select) {
            $this->where[] = $value ?? $condition;
            return $select;
        });
        $select->method('forUpdate')->willReturnCallback(function (bool $flag) use ($select) {
            self::assertTrue($flag);
            $this->calls[] = ['lock', array_splice($this->where, 0)];
            return $select;
        });
        return $select;
    }

    private function group(string $incrementId, string $sku, string $sourceCode, float $ledger): array
    {
        return [
            'object_increment_id' => $incrementId,
            'stock_id' => 5,
            'sku' => $sku,
            'source_code' => $sourceCode,
            'ledger' => $ledger,
        ];
    }

    private function written(string $incrementId, string $sku, string $sourceCode, float $quantity): array
    {
        return [
            'object_increment_id' => $incrementId,
            'stock_id' => 5,
            'sku' => $sku,
            'source_code' => $sourceCode,
            'quantity' => $quantity,
        ];
    }

    private function row(string $incrementId, string $sku, string $sourceCode, float $balance): array
    {
        return [
            'object_increment_id' => $incrementId,
            'stock_id' => 5,
            'sku' => $sku,
            'source_code' => $sourceCode,
            'balance' => $balance,
        ];
    }
}
