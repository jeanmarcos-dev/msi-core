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
    /**
     * @var array
     */
    private array $queries = [];

    protected function setUp(): void
    {
        $this->queries = [];
    }

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

        $connection
            ->method('select')
            ->willReturn($select);

        $select
            ->method('from')
            ->willReturnSelf();

        $select
            ->method('where')
            ->willReturnSelf();

        $storedItems = [
            ['source_item_id' => 1111, 'source_code' => 'warehouse', 'sku' => 'EXISTING-1'],
            ['source_item_id' => 2222, 'source_code' => 'warehouse', 'sku' => 'EXISTING-2'],
        ];

        $connection
            ->method('fetchAll')
            ->willReturn($storedItems);
        $connection->method('quoteIdentifier')->willReturnCallback(fn (string $name): string => '`' . $name . '`');

        $insertedBind = null;
        $upsertedBind = null;
        $connection
            ->expects($this->exactly(2))
            ->method('query')
            ->willReturnCallback(function (string $sql, array $bind) use (&$insertedBind, &$upsertedBind): void {
                if (str_contains($sql, '`source_item_id`')) {
                    $upsertedBind = $bind;
                    return;
                }
                $insertedBind = $bind;
            });
        $connection->expects($this->never())->method('update');

        $sourceItems = [
            $this->createSourceItem('warehouse', 'EXISTING-1', 10, 1),
            $this->createSourceItem('warehouse', 'EXISTING-2', 20, 1),
            $this->createSourceItem('warehouse', 'NEW-1', 30, 1),
            $this->createSourceItem('warehouse', 'NEW-2', 40, 1),
        ];

        $saveMultiple = new SaveMultiple($resourceConnection);
        $saveMultiple->execute($sourceItems);

        $this->assertIsArray($insertedBind);
        $this->assertContains('NEW-1', $insertedBind);
        $this->assertContains('NEW-2', $insertedBind);
        $this->assertNotContains('EXISTING-1', $insertedBind);
        $this->assertNotContains('EXISTING-2', $insertedBind);

        $this->assertSame(
            [1111, 'warehouse', 'EXISTING-1', 10.0, 1, 2222, 'warehouse', 'EXISTING-2', 20.0, 1],
            $upsertedBind
        );
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
        $upserts = [];
        $connection = $this->createConnection(
            [['source_item_id' => 7, 'source_code' => 'src_a', 'sku' => 'ABC']],
            $insertedBind,
            $upserts
        );
        $saveMultiple = new SaveMultiple($this->createResourceConnection($connection));
        $saveMultiple->execute([
            $this->createSourceItem('src_a', 'ABC', 5, 1),
            $this->createSourceItem('src_b', 'ABC', 3, 1),
        ]);

        $this->assertSame(['src_b', 'ABC', 3.0, 1], $insertedBind);
        $this->assertSame([7, 'src_a', 'ABC', 5.0, 1], $upserts[0]);
    }

    public function testNewItemsAreInsertedInTheOrderTheyWereGiven(): void
    {
        $insertedBind = null;
        $upserts = [];
        $connection = $this->createConnection(
            [['source_item_id' => 7, 'source_code' => 'src_a', 'sku' => 'OLD']],
            $insertedBind,
            $upserts
        );

        $saveMultiple = new SaveMultiple($this->createResourceConnection($connection));
        $saveMultiple->execute([
            $this->createSourceItem('src_b', 'X', 1, 1),
            $this->createSourceItem('src_a', 'OLD', 2, 1),
            $this->createSourceItem('src_a', 'Y', 3, 1),
            $this->createSourceItem('src_b', 'Z', 4, 1),
        ]);

        $this->assertSame(['src_b', 'X', 1.0, 1, 'src_a', 'Y', 3.0, 1, 'src_b', 'Z', 4.0, 1], $insertedBind);
        $this->assertSame([[7, 'src_a', 'OLD', 2.0, 1]], $upserts);
    }

    public function testExistingItemsAreUpsertedInChunksKeepingTheirOrder(): void
    {
        $stored = [];
        $items = [];
        for ($i = 1; $i <= 2500; $i++) {
            $stored[] = ['source_item_id' => $i, 'source_code' => 'src_a', 'sku' => 'SKU-' . $i];
            $items[] = $this->createSourceItem('src_a', 'SKU-' . $i, $i, 1);
        }
        $insertedBind = null;
        $upserts = [];
        $connection = $this->createConnection($stored, $insertedBind, $upserts);
        $connection->expects($this->never())->method('update');

        $saveMultiple = new SaveMultiple($this->createResourceConnection($connection));
        $saveMultiple->execute($items);

        $this->assertNull($insertedBind);
        $this->assertCount(2500, $upserts);
        $this->assertSame(range(1, 2500), array_column($upserts, 0));
        $this->assertCount(3, $this->queries);
    }

    public function testUpsertSqlKeysOnTheSourceItemIdAndRewritesTheValues(): void
    {
        $insertedBind = null;
        $upserts = [];
        $connection = $this->createConnection(
            [['source_item_id' => 7, 'source_code' => 'src_a', 'sku' => 'ABC']],
            $insertedBind,
            $upserts
        );

        $saveMultiple = new SaveMultiple($this->createResourceConnection($connection));
        $saveMultiple->execute([$this->createSourceItem('src_a', 'ABC', 5, 1)]);

        $this->assertStringContainsString(
            '(`source_item_id`, `source_code`, `sku`, `quantity`, `status`)',
            $this->queries[0][0]
        );
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $this->queries[0][0]);
        $this->assertStringContainsString('`quantity` = VALUES(`quantity`)', $this->queries[0][0]);
        $this->assertStringContainsString('`status` = VALUES(`status`)', $this->queries[0][0]);
    }

    private function createConnection(
        array $storedItems,
        ?array &$insertedBind,
        array &$upserts = []
    ): AdapterInterface&MockObject {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn($storedItems);
        $connection->method('quoteIdentifier')->willReturnCallback(fn (string $name): string => '`' . $name . '`');
        $connection->method('query')
            ->willReturnCallback(function (string $sql, array $bind) use (&$insertedBind, &$upserts): void {
                $this->queries[] = [$sql, $bind];
                if (str_contains($sql, '`source_item_id`')) {
                    foreach (array_chunk($bind, 5) as $row) {
                        $upserts[] = $row;
                    }
                    return;
                }
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
