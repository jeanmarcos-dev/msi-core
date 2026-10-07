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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TransferInventoryPartiallyTest extends TestCase
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
     * @var array
     */
    private $updates = [];

    /**
     * @var TransferInventoryPartially
     */
    private $model;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->select = $this->createMock(Select::class);
        $this->select->method('from')->willReturnSelf();
        $this->select->method('where')->willReturnSelf();
        $this->select->method('forUpdate')->willReturnSelf();
        $this->connection->method('select')->willReturn($this->select);
        $this->connection->method('quoteInto')->willReturnCallback(
            fn (string $text, $value) => str_replace('?', (string)$value, $text)
        );

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->model = new TransferInventoryPartially($resourceConnection);
    }

    public function testMovesTheQuantityWithRelativeUpdatesOnLockedRows(): void
    {
        $this->select->expects(self::once())->method('forUpdate')->with(true);
        $this->givenLockedSources(['slr_a', 'slr_b']);
        $this->givenUpdatedRows([1, 1]);
        $this->connection->expects(self::once())->method('commit');
        $this->connection->expects(self::never())->method('rollBack');

        $this->model->execute($this->item('SKU-1', 3.0), 'slr_a', 'slr_b');

        self::assertSame(
            [
                ['quantity' => 'quantity - 3', 'source_code=?' => 'slr_a', 'sku=?' => 'SKU-1', 'quantity >= ?' => 3.0],
                ['quantity' => 'quantity + 3', 'status' => 1, 'source_code=?' => 'slr_b', 'sku=?' => 'SKU-1'],
            ],
            $this->updates
        );
    }

    public function testRejectsAQuantityTheOriginNoLongerHas(): void
    {
        $this->givenLockedSources(['slr_a', 'slr_b']);
        $this->givenUpdatedRows([0]);
        $this->connection->expects(self::once())->method('rollBack');
        $this->connection->expects(self::never())->method('commit');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Requested transfer amount for sku SKU-1 is not available');

        try {
            $this->model->execute($this->item('SKU-1', 3.0), 'slr_a', 'slr_b');
        } finally {
            self::assertCount(1, $this->updates);
        }
    }

    public function testAbortsWithoutTouchingTheOriginWhenTheDestinationIsGone(): void
    {
        $this->givenLockedSources(['slr_a']);
        $this->connection->expects(self::never())->method('update');
        $this->connection->expects(self::once())->method('rollBack');

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Source item for SKU-1 and slr_b does not exist');

        $this->model->execute($this->item('SKU-1', 3.0), 'slr_a', 'slr_b');
    }

    public function testAbortsWhenTheOriginIsGone(): void
    {
        $this->givenLockedSources(['slr_b']);
        $this->connection->expects(self::never())->method('update');

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Source item for SKU-1 and slr_a does not exist');

        $this->model->execute($this->item('SKU-1', 3.0), 'slr_a', 'slr_b');
    }

    /**
     * @dataProvider nonPositiveQuantities
     */
    public function testRejectsANonPositiveQuantityBeforeTouchingTheDatabase(float $qty): void
    {
        $this->connection->expects(self::never())->method('beginTransaction');
        $this->connection->expects(self::never())->method('update');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Transfer quantity for sku SKU-1 must be greater than zero');

        $this->model->execute($this->item('SKU-1', $qty), 'slr_a', 'slr_b');
    }

    public static function nonPositiveQuantities(): array
    {
        return ['zero' => [0.0], 'negative' => [-5.0]];
    }

    private function givenLockedSources(array $sourceCodes): void
    {
        $this->connection->method('fetchCol')->with($this->select)->willReturn($sourceCodes);
    }

    private function givenUpdatedRows(array $affectedRows): void
    {
        $this->connection->method('update')->willReturnCallback(
            function (string $table, array $bind, array $where) use (&$affectedRows) {
                $this->updates[] = array_map(fn ($value) => is_object($value) ? (string)$value : $value, $bind)
                    + $where;
                return array_shift($affectedRows);
            }
        );
    }

    private function item(string $sku, float $qty): PartialInventoryTransferItemInterface
    {
        $item = $this->createMock(PartialInventoryTransferItemInterface::class);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQty')->willReturn($qty);
        return $item;
    }
}
