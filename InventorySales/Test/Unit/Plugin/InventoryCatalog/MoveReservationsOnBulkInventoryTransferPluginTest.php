<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryCatalog;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use Magento\InventorySales\Model\SourceReservation\MoveSourceReservations;
use Magento\InventorySales\Plugin\InventoryCatalog\MoveReservationsOnBulkInventoryTransferPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MoveReservationsOnBulkInventoryTransferPluginTest extends TestCase
{
    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $config;

    /**
     * @var MoveSourceReservations|MockObject
     */
    private $moveSourceReservations;

    /**
     * @var array
     */
    private $calls = [];

    /**
     * @var MoveReservationsOnBulkInventoryTransferPlugin
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->config = $this->createMock(SourceReservationsConfig::class);
        $this->moveSourceReservations = $this->createMock(MoveSourceReservations::class);
        $locks = $this->createMock(AcquireStockItemLocks::class);
        $locks->method('executeForSources')->willReturnCallback(function (array $skus, array $sources) {
            $this->calls[] = 'lock ' . implode(',', $skus) . ' @ ' . implode(',', $sources);
        });
        $locks->method('releaseAll')->willReturnCallback(function () {
            $this->calls[] = 'release';
        });
        $connection = $this->createMock(AdapterInterface::class);
        foreach (['beginTransaction' => 'begin', 'commit' => 'commit', 'rollBack' => 'rollback'] as $method => $call) {
            $connection->method($method)->willReturnCallback(function () use ($call, $connection) {
                $this->calls[] = $call;
                return $connection;
            });
        }
        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);

        $this->plugin = new MoveReservationsOnBulkInventoryTransferPlugin(
            $this->config,
            $locks,
            $resourceConnection,
            $this->moveSourceReservations
        );
    }

    public function testMovesTheReservationsWithTheStockUnderTheSourceLocks(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->moveSourceReservations->method('execute')->willReturnCallback(function () {
            $this->calls[] = 'move reservations';
        });

        $this->transfer(function () {
            $this->calls[] = 'move stock';
        });

        self::assertSame(
            ['lock SLR-1,SLR-2 @ slr_a,slr_b', 'begin', 'move reservations', 'move stock', 'commit', 'release'],
            $this->calls
        );
    }

    public function testRollsBackAndKeepsTheStockWhenTheReservationsCannotMove(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->moveSourceReservations->method('execute')
            ->willThrowException(new ValidationException(__('not assigned')));

        $this->expectException(ValidationException::class);

        try {
            $this->transfer(function () {
                $this->calls[] = 'move stock';
            });
        } finally {
            self::assertSame(['lock SLR-1,SLR-2 @ slr_a,slr_b', 'begin', 'rollback', 'release'], $this->calls);
        }
    }

    public function testOnlyMovesTheStockWhenSourceReservationsAreDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->moveSourceReservations->expects(self::never())->method('execute');

        $this->transfer(function () {
            $this->calls[] = 'move stock';
        });

        self::assertSame(['move stock'], $this->calls);
    }

    private function transfer(callable $proceed): void
    {
        $this->plugin->aroundExecute(
            $this->createMock(BulkInventoryTransfer::class),
            $proceed,
            ['SLR-1', 'SLR-2'],
            'slr_a',
            'slr_b',
            false
        );
    }
}
