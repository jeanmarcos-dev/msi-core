<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventorySales\Model\ResourceModel\InventoryLockName;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\ReservationClampLock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReservationClampLockTest extends TestCase
{
    /**
     * @var AdapterInterface|MockObject
     */
    private $connection;

    /**
     * @var ReservationClampLock
     */
    private $lock;

    /**
     * @var InventoryLockName
     */
    private InventoryLockName $lockName;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn('shop');
        $this->lockName = new InventoryLockName($deploymentConfig);
        $this->lock = new ReservationClampLock($resourceConnection, $this->lockName);
    }

    public function testAcquiresOneLockPerDistinctStockSku(): void
    {
        $calls = [];
        $this->connection->method('fetchOne')->willReturnCallback(
            function (string $sql, array $bind) use (&$calls) {
                $calls[] = $bind[0];
                return '1';
            }
        );

        $names = $this->lock->acquire([
            ['stock_id' => 2, 'sku' => 'A'],
            ['stock_id' => 2, 'sku' => 'A'],
            ['stock_id' => 2, 'sku' => 'B'],
        ]);

        self::assertCount(2, $names);
        self::assertSame($names, array_values($names));
        self::assertSame(array_values(array_unique($names)), $names);
        self::assertCount(2, $calls);
    }

    public function testLockNamesComeFromTheInstallationScopedBuilderInGlobalOrder(): void
    {
        $this->connection->method('fetchOne')->willReturn('1');
        $expected = [
            $this->lockName->build(InventoryLockName::CLAMP, '2', 'B'),
            $this->lockName->build(InventoryLockName::CLAMP, '2', 'A'),
        ];
        sort($expected, SORT_STRING);

        $names = $this->lock->acquire([
            ['stock_id' => 2, 'sku' => 'B'],
            ['stock_id' => 2, 'sku' => 'A'],
        ]);

        self::assertSame($expected, $names);
    }

    public function testReleaseReleasesEachName(): void
    {
        $released = 0;
        $this->connection->method('fetchOne')->willReturnCallback(
            function () use (&$released) {
                $released++;
                return '1';
            }
        );

        $this->lock->release(['inv_rsv_clamp_2_x', 'inv_rsv_clamp_2_y']);

        self::assertSame(2, $released);
    }

    public function testAcquireEmptyReturnsEmpty(): void
    {
        $this->connection->expects(self::never())->method('fetchOne');

        self::assertSame([], $this->lock->acquire([]));
    }
}
