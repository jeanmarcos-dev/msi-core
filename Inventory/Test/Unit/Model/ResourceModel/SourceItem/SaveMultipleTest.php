<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Test\Unit\Model\ResourceModel\SourceItem;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;
use Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple
 */
class SaveMultipleTest extends TestCase
{
    public function testSeparateExistingAndNewItems(): void
    {
        $select = $this->createStub(Select::class);
        $connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);

        $resourceConnection
            ->method('getConnection')
            ->willReturn($connection);

        $resourceConnection
            ->method('getTableName')
            ->with(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM)
            ->willReturn('inventory_source_item');

        // The code does: $connection->select()->from(...)->where(...)->where(...)
        $connection
            ->method('select')
            ->willReturn($select);

        $select
            ->method('from')
            ->willReturnSelf();

        $select
            ->method('where')
            ->willReturnSelf();

        // Two items already exist in the DB
        $storedItems = [
            ['source_item_id' => 1111, 'source_code' => 'warehouse', 'sku' => 'EXISTING-1'],
            ['source_item_id' => 2222, 'source_code' => 'warehouse', 'sku' => 'EXISTING-2'],
        ];

        $connection
            ->method('fetchAll')
            ->willReturn($storedItems);

        // Capture what gets INSERTed (new items)
        $insertedBind = null;
        $connection
            ->expects($this->once())
            ->method('query')
            ->willReturnCallback(function (string $sql, array $bind) use (&$insertedBind): void {
                $insertedBind = $bind;
            });

        // Capture what gets UPDATED (existing items)
        $updatedItems = [];
        $connection
            ->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(function (
                string $tableName,
                array $bind,
                array $condition,
            ) use (&$updatedItems): void {
                $updatedItems[] = ['bind' => $bind, 'condition' => $condition];
            });

        // Build 4 source items: 2 existing + 2 new
        $sourceItems = [
            $this->createSourceItem('warehouse', 'EXISTING-1', 10, 1),
            $this->createSourceItem('warehouse', 'EXISTING-2', 20, 1),
            $this->createSourceItem('warehouse', 'NEW-1', 30, 1),
            $this->createSourceItem('warehouse', 'NEW-2', 40, 1),
        ];

        $saveMultiple = new SaveMultiple($resourceConnection);
        $saveMultiple->execute($sourceItems);

        // ASSERT — 2 new items should have been INSERTed
        $this->assertIsArray($insertedBind);
        $this->assertContains('NEW-1', $insertedBind);
        $this->assertContains('NEW-2', $insertedBind);
        $this->assertNotContains('EXISTING-1', $insertedBind);
        $this->assertNotContains('EXISTING-2', $insertedBind);

        // ASSERT — 2 existing items should have been UPDATED
        $this->assertContains('EXISTING-1', $updatedItems[0]['bind']);
        $this->assertContains(1111, $updatedItems[0]['condition']);
        $this->assertContains('EXISTING-2', $updatedItems[1]['bind']);
        $this->assertContains(2222, $updatedItems[1]['condition']);
    }

    public function testSameNewSkuInTwoSourcesIsInsertedForBoth(): void
    {
        $insertedBind = null;
        $connection = $this->createConnection([], $insertedBind);
        $connection->expects($this->never())->method('update');

        $saveMultiple = new SaveMultiple($this->createResourceConnection($connection));
        $saveMultiple->execute([
            $this->createSourceItem('src_a', 'ABC', 1, 1),
            $this->createSourceItem('src_b', 'ABC', 2, 1),
        ]);

        $this->assertSame(['src_a', 'ABC', 1.0, 1, 'src_b', 'ABC', 2.0, 1], $insertedBind);
    }

    public function testSkuExistingInOneSourceIsUpdatedThereAndInsertedInTheOther(): void
    {
        $insertedBind = null;
        $connection = $this->createConnection(
            [['source_item_id' => 7, 'source_code' => 'src_a', 'sku' => 'ABC']],
            $insertedBind
        );
        $updates = [];
        $connection->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (string $table, array $bind, array $condition) use (&$updates): void {
                $updates[] = [$bind, $condition];
            });

        $saveMultiple = new SaveMultiple($this->createResourceConnection($connection));
        $saveMultiple->execute([
            $this->createSourceItem('src_a', 'ABC', 5, 1),
            $this->createSourceItem('src_b', 'ABC', 3, 1),
        ]);

        $this->assertSame(['src_b', 'ABC', 3.0, 1], $insertedBind);
        $this->assertCount(1, $updates);
        $this->assertContains(5.0, $updates[0][0]);
        $this->assertContains(7, $updates[0][1]);
    }

    public function testNewItemsAreInsertedInTheOrderTheyWereGiven(): void
    {
        $insertedBind = null;
        $connection = $this->createConnection(
            [['source_item_id' => 7, 'source_code' => 'src_a', 'sku' => 'OLD']],
            $insertedBind
        );
        $connection->expects($this->once())
            ->method('update')
            ->with('inventory_source_item', $this->anything(), ['source_item_id = ?' => 7]);

        $saveMultiple = new SaveMultiple($this->createResourceConnection($connection));
        $saveMultiple->execute([
            $this->createSourceItem('src_b', 'X', 1, 1),
            $this->createSourceItem('src_a', 'OLD', 2, 1),
            $this->createSourceItem('src_a', 'Y', 3, 1),
            $this->createSourceItem('src_b', 'Z', 4, 1),
        ]);

        $this->assertSame(['src_b', 'X', 1.0, 1, 'src_a', 'Y', 3.0, 1, 'src_b', 'Z', 4.0, 1], $insertedBind);
    }

    private function createConnection(array $storedItems, ?array &$insertedBind): AdapterInterface&MockObject
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($storedItems);
        $connection->method('query')
            ->willReturnCallback(function (string $sql, array $bind) use (&$insertedBind): void {
                $insertedBind = $bind;
            });

        return $connection;
    }

    private function createResourceConnection(AdapterInterface $connection): ResourceConnection&Stub
    {
        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturn('inventory_source_item');

        return $resourceConnection;
    }

    /**
     * Helper to create a mock SourceItemInterface.
     *
     * @return SourceItemInterface&MockObject
     */
    private function createSourceItem(
        string $sourceCode,
        string $sku,
        int|float $quantity,
        int $status,
    ): SourceItemInterface {
        $item = $this->createStub(SourceItemInterface::class);
        $item->method('getSourceCode')->willReturn($sourceCode);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQuantity')->willReturn((float) $quantity);
        $item->method('getStatus')->willReturn($status);
        return $item;
    }
}
