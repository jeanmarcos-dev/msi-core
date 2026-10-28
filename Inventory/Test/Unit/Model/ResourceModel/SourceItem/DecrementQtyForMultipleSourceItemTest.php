<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\Inventory\Test\Unit\Model\ResourceModel\SourceItem;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Inventory\Model\ResourceModel\SourceItem\DecrementQtyForMultipleSourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DecrementQtyForMultipleSourceItemTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var DecrementQtyForMultipleSourceItem
     */
    private $decrement;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $resource->method('getTableName')->willReturn('inventory_source_item');

        $this->decrement = new DecrementQtyForMultipleSourceItem($resource);
    }

    public function testAStatusIsWrittenWithTheQuantity(): void
    {
        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                ['inventory_source_item'],
                self::callback(fn (array $bind) => array_keys($bind) === ['quantity', 'status']
                    && (string)$bind['quantity'] === 'quantity - 2'
                    && $bind['status'] === SourceItemInterface::STATUS_OUT_OF_STOCK),
                ['source_code = ?' => 'sri_a', 'sku = ?' => 'SKU-1']
            );

        $this->decrement->execute([
            [
                'source_item' => $this->sourceItem(),
                'qty_to_decrement' => 2,
                'status' => SourceItemInterface::STATUS_OUT_OF_STOCK,
            ],
        ]);
    }

    public function testWithoutAStatusOnlyTheQuantityIsWritten(): void
    {
        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                ['inventory_source_item'],
                self::callback(fn (array $bind) => array_keys($bind) === ['quantity']
                    && (string)$bind['quantity'] === 'quantity - 2'),
                ['source_code = ?' => 'sri_a', 'sku = ?' => 'SKU-1']
            );

        $this->decrement->execute([['source_item' => $this->sourceItem(), 'qty_to_decrement' => 2]]);
    }

    private function sourceItem(): SourceItemInterface
    {
        $sourceItem = $this->createStub(SourceItemInterface::class);
        $sourceItem->method('getSourceCode')->willReturn('sri_a');
        $sourceItem->method('getSku')->willReturn('SKU-1');

        return $sourceItem;
    }
}
