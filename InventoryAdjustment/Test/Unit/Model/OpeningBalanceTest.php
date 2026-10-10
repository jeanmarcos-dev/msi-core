<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\InventoryAdjustment\Model\OpeningBalance;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class OpeningBalanceTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var Select|MockObject
     */
    private $select;

    /**
     * @var AdjustmentWriter|MockObject
     */
    private $writer;

    /**
     * @var array
     */
    private array $conditions = [];

    /**
     * @var array
     */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('order')->willReturnSelf();
        $this->select->method('limit')->willReturnSelf();
        $this->select->method('forUpdate')->willReturnCallback(function (bool $flag) {
            $this->calls[] = $flag ? 'lock' : 'read';
            return $this->select;
        });
        $this->select->method('where')->willReturnCallback(function (string $condition, $value = null) {
            $this->conditions[] = [$condition, $value];
            return $this->select;
        });
        $this->select->method('__toString')->willReturn('HISTORY ROW');

        $this->connection = $this->createMock(AdapterInterface::class);
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('beginTransaction')->willReturnCallback(function () {
            $this->calls[] = 'begin';
            return $this->connection;
        });
        $this->connection->method('commit')->willReturnCallback(function () {
            $this->calls[] = 'commit';
            return $this->connection;
        });
        $this->connection->method('rollBack')->willReturnCallback(function () {
            $this->calls[] = 'rollback';
            return $this->connection;
        });

        $this->writer = $this->createMock(AdjustmentWriter::class);
    }

    public function testWritesOneOpeningRowPerItemWithTheItemQuantity(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['3', '8'], ['3', '8'], []);
        $this->connection->method('fetchAll')->willReturn(
            [$this->item(3, 'src_a', 'SKU-1', '5.0000'), $this->item(8, 'src_b', 'SKU-1', '-2.5000')]
        );
        $this->writer->expects(self::once())->method('write')->with([
            $this->openingRow('src_a', 'SKU-1', 5.0),
            $this->openingRow('src_b', 'SKU-1', -2.5),
        ]);

        self::assertSame(2, $this->openingBalance()->write('request-1', 1000));
    }

    public function testPagesBySourceItemIdUntilNoCandidateIsLeft(): void
    {
        $this->connection->expects(self::exactly(5))
            ->method('fetchCol')
            ->willReturnOnConsecutiveCalls(['3', '8'], ['3', '8'], ['12'], ['12'], []);
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$this->item(3, 'src_a', 'SKU-1', '1'), $this->item(8, 'src_a', 'SKU-2', '1')],
            [$this->item(12, 'src_b', 'SKU-1', '1')]
        );
        $this->writer->expects(self::exactly(2))->method('write');

        self::assertSame(3, $this->openingBalance()->write('request-1', 2));
        self::assertSame(
            [['item.source_item_id > ?', 0], ['item.source_item_id > ?', 8], ['item.source_item_id > ?', 12]],
            array_values(array_filter($this->conditions, fn (array $c) => $c[0] === 'item.source_item_id > ?'))
        );
    }

    public function testLocksOnlyTheChosenItemsInsideATransaction(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['3', '8'], ['3', '8'], []);
        $this->connection->method('fetchAll')->willReturn([$this->item(3, 'src_a', 'SKU-1', '1')]);

        $this->openingBalance()->write('request-1', 1000);

        self::assertSame(['begin', 'lock', 'commit'], $this->calls);
        self::assertContains(['item.source_item_id IN (?)', ['3', '8']], $this->conditions);
    }

    public function testSkipsItemsThatGotHistoryWhileWaitingForTheLock(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['3', '8'], ['3', '8'], []);
        $this->connection->method('fetchAll')->willReturn([$this->item(8, 'src_b', 'SKU-1', '4')]);
        $this->writer->expects(self::once())->method('write')->with([$this->openingRow('src_b', 'SKU-1', 4.0)]);

        self::assertSame(1, $this->openingBalance()->write('request-1', 1000));
    }

    public function testSelectsOnlyItemsWithStockAndNoHistory(): void
    {
        $this->connection->method('fetchCol')->willReturnOnConsecutiveCalls(['3'], ['3'], []);
        $this->connection->method('fetchAll')->willReturn([]);
        $this->writer->expects(self::never())->method('write');

        self::assertSame(0, $this->openingBalance()->write('request-1', 1000));
        self::assertSame(
            3,
            count(array_filter($this->conditions, fn (array $c) => $c === ['item.quantity <> 0', null]))
        );
        self::assertSame(
            3,
            count(array_filter($this->conditions, fn (array $c) => $c === ['NOT EXISTS (HISTORY ROW)', null]))
        );
        self::assertContains(['history.source_code = item.source_code', null], $this->conditions);
        self::assertContains(['history.sku = item.sku', null], $this->conditions);
    }

    public function testRollsBackTheBatchWhenWritingFails(): void
    {
        $this->connection->method('fetchCol')->willReturn(['3']);
        $this->connection->method('fetchAll')->willReturn([$this->item(3, 'src_a', 'SKU-1', '1')]);
        $this->writer->method('write')->willThrowException(new RuntimeException('Deadlock'));

        try {
            $this->openingBalance()->write('request-1', 1000);
            self::fail('The write failure must reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame('Deadlock', $exception->getMessage());
        }
        self::assertSame(['begin', 'lock', 'rollback'], $this->calls);
    }

    private function item(int $id, string $source, string $sku, string $quantity): array
    {
        return ['source_item_id' => (string)$id, 'source_code' => $source, 'sku' => $sku, 'quantity' => $quantity];
    }

    private function openingRow(string $source, string $sku, float $quantity): array
    {
        return [
            'source_code' => $source,
            'sku' => $sku,
            'state' => 'available',
            'delta' => $quantity,
            'quantity_after' => $quantity,
            'status_before' => null,
            'status_after' => null,
            'reason' => 'other',
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_label' => null,
            'reference_type' => null,
            'reference_id' => null,
            'request_id' => 'request-1',
            'note' => 'opening_balance',
        ];
    }

    private function openingBalance(): OpeningBalance
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturnArgument(0);

        return new OpeningBalance($resource, $this->writer);
    }
}
