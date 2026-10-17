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
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationLedger;
use PHPUnit\Framework\TestCase;

class GetOrderReservationLedgerTest extends TestCase
{
    /**
     * @var array<int,array>
     */
    private array $where = [];

    public function testBoundsTheLedgerToReservationsUpToTheCutoff(): void
    {
        $this->ledger()->execute('000000042', 900);

        self::assertContains(['reservation_id <= ?', 900], $this->where);
    }

    public function testReadsTheWholeLedgerWithoutACutoff(): void
    {
        $this->ledger()->execute('000000042');

        self::assertCount(1, $this->where);
    }

    /**
     * @return GetOrderReservationLedger
     */
    private function ledger(): GetOrderReservationLedger
    {
        $select = $this->createMock(Select::class);
        foreach (['from', 'group', 'having'] as $method) {
            $select->method($method)->willReturnSelf();
        }
        $select->method('where')->willReturnCallback(
            function (string $condition, $value) use ($select) {
                $this->where[] = [$condition, $value];
                return $select;
            }
        );
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        return new GetOrderReservationLedger($resourceConnection);
    }
}
