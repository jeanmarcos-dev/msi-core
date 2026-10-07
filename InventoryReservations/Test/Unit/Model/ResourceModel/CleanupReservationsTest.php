<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\InventoryReservations\Model\ResourceModel\CleanupReservations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CleanupReservationsTest extends TestCase
{
    private const OBJECT_TYPE_KEY = "CAST(JSON_EXTRACT(metadata, '$.object_type') AS CHAR)";

    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var array
     */
    private $groupCalls = [];

    /**
     * @var array
     */
    private $whereCalls = [];

    /**
     * @var array
     */
    private $fromCalls = [];

    /**
     * @var array
     */
    private $deletedChunks = [];

    /**
     * @var CleanupReservations
     */
    private $model;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);

        $select = $this->createMock(Select::class);
        $select->method('having')->willReturnSelf();
        $select->method('from')->willReturnCallback(function ($table, $columns) use ($select) {
            $this->fromCalls[] = $columns;
            return $select;
        });
        $select->method('group')->willReturnCallback(function (...$arguments) use ($select) {
            $this->groupCalls[] = $arguments;
            return $select;
        });
        $select->method('where')->willReturnCallback(function ($condition, $value = null) use ($select) {
            $this->whereCalls[] = [$condition, $value];
            return $select;
        });
        $this->connection->method('select')->willReturn($select);
        $this->connection->method('delete')
            ->willReturnCallback(function (string $table, array $condition) {
                $this->deletedChunks[] = current($condition);
                return 0;
            });

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->model = new CleanupReservations($resourceConnection, 1024);
    }

    public function testDeletesReservationsInChunks(): void
    {
        $orderGroups = [];
        for ($id = 1; $id <= 25000; $id += 2) {
            $orderGroups[] = $this->group([$id, $id + 1]);
        }
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls($orderGroups, []);

        $this->model->execute();

        self::assertSame([10000, 10000, 5000], array_map('count', $this->deletedChunks));
        self::assertSame(range(1, 25000), array_merge(...$this->deletedChunks));
    }

    public function testDoesNotDeleteWhenThereAreNoCompensatedReservations(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);

        $this->connection->expects(self::never())->method('delete');

        $this->model->execute();
    }

    public function testDeletesDuplicatedReservationIdsOnlyOnce(): void
    {
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$this->group([1, 2, 3])],
            [$this->group([3, 4])]
        );

        $this->model->execute();

        self::assertSame([[1, 2, 3, 4]], $this->deletedChunks);
    }

    public function testDoesNotSplitACompensatedGroupAcrossDeleteStatements(): void
    {
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$this->group(range(1, 9999)), $this->group([10000, 10001, 10002])],
            []
        );

        $this->model->execute();

        self::assertSame([9999, 3], array_map('count', $this->deletedChunks));
        self::assertSame([10000, 10001, 10002], end($this->deletedChunks));
    }

    public function testKeepsGroupLargerThanChunkSizeInASingleDeleteStatement(): void
    {
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$this->group(range(1, 10001))],
            []
        );

        $this->model->execute();

        self::assertSame([10001], array_map('count', $this->deletedChunks));
    }

    public function testGroupsByObjectTypeAndSourceCodeInASingleGroupClause(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);

        $this->model->execute();

        self::assertCount(2, $this->groupCalls);
        foreach ($this->groupCalls as $arguments) {
            self::assertCount(1, $arguments, 'Select::group() only reads its first argument.');
            self::assertIsArray($arguments[0]);
            self::assertCount(3, $arguments[0]);
            self::assertContains(self::OBJECT_TYPE_KEY, $arguments[0]);
            self::assertContains('source_code', $arguments[0]);
        }
    }

    public function testPassesCastKeysAsExpressionsSoTheirAsIsNotReadAsAnAlias(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);

        $this->model->execute();

        $groupColumns = array_filter($this->fromCalls, fn ($columns) => isset($columns['object_key']));
        self::assertCount(2, $groupColumns);
        foreach ($groupColumns as $columns) {
            self::assertInstanceOf(Expression::class, $columns['object_key']);
            self::assertInstanceOf(Expression::class, $columns['object_type']);
        }
    }

    public function testSkipsReservationsWithoutTheGroupedKey(): void
    {
        $this->connection->method('fetchAll')->willReturn([]);

        $this->model->execute();

        $conditions = array_column($this->whereCalls, 0);
        self::assertContains("CAST(JSON_EXTRACT(metadata, '$.object_id') AS CHAR) IS NOT NULL", $conditions);
        self::assertContains(
            "CAST(JSON_EXTRACT(metadata, '$.object_increment_id') AS CHAR) IS NOT NULL",
            $conditions
        );
    }

    public function testReadsTheWholeGroupWhenTheConcatenatedIdsWereTruncated(): void
    {
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$this->group([1, 2], 5, '"7"', '"order"', 'slr_a')],
            []
        );
        $this->connection->expects(self::once())->method('fetchCol')->willReturn(['1', '2', '3', '4', '5']);

        $this->model->execute();

        self::assertSame([[1, 2, 3, 4, 5]], $this->deletedChunks);
        self::assertContains(["CAST(JSON_EXTRACT(metadata, '$.object_id') AS CHAR) = ?", '"7"'], $this->whereCalls);
        self::assertContains([self::OBJECT_TYPE_KEY . ' = ?', '"order"'], $this->whereCalls);
        self::assertContains(['source_code = ?', 'slr_a'], $this->whereCalls);
    }

    public function testReadsATruncatedGroupWithoutObjectTypeOrSource(): void
    {
        $this->connection->method('fetchAll')->willReturnOnConsecutiveCalls(
            [$this->group([1], 3, '"7"', null, null)],
            []
        );
        $this->connection->method('fetchCol')->willReturn(['1', '2', '3']);

        $this->model->execute();

        self::assertSame([[1, 2, 3]], $this->deletedChunks);
        self::assertContains([self::OBJECT_TYPE_KEY . ' IS NULL', null], $this->whereCalls);
        self::assertContains(['source_code IS NULL', null], $this->whereCalls);
    }

    private function group(
        array $reservationIds,
        ?int $reservationCount = null,
        string $objectKey = '"1"',
        ?string $objectType = '"order"',
        ?string $sourceCode = null
    ): array {
        return [
            'reservation_ids' => implode(',', $reservationIds),
            'reservation_count' => (string)($reservationCount ?? count($reservationIds)),
            'object_key' => $objectKey,
            'object_type' => $objectType,
            'source_code' => $sourceCode,
        ];
    }
}
