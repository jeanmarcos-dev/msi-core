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
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderExistsByIncrementId;
use PHPUnit\Framework\TestCase;

class OrderExistsByIncrementIdTest extends TestCase
{
    public function testLooksTheIncrementIdUpInSalesOrder(): void
    {
        $where = [];
        $exists = $this->model('42', $where);

        self::assertTrue($exists->execute('000000042'));
        self::assertSame(['increment_id = ?', '000000042'], $where);
    }

    public function testReportsAMissingOrder(): void
    {
        $where = [];

        self::assertFalse($this->model(false, $where)->execute('000000042'));
    }

    /**
     * @param string|false $entityId
     * @param array $where
     * @return OrderExistsByIncrementId
     */
    private function model($entityId, array &$where): OrderExistsByIncrementId
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->with('sales_order', ['entity_id'])->willReturnSelf();
        $select->method('where')->willReturnCallback(
            function (string $condition, $value) use ($select, &$where) {
                $where = [$condition, $value];
                return $select;
            }
        );
        $select->method('limit')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchOne')->willReturn($entityId);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        return new OrderExistsByIncrementId($resourceConnection);
    }
}
