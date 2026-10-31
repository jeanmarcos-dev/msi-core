<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryReservationsApi;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\LockWaitException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderBalanceGuard;
use Magento\InventorySales\Model\SourceReservation\ReservationOrderIncrementId;
use Magento\InventorySales\Plugin\InventoryReservationsApi\RecordOrderBalancePlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecordOrderBalancePluginTest extends TestCase
{
    private const STOCK_ID = 10;

    /**
     * @var OrderBalanceGuard|MockObject
     */
    private $orderBalanceGuard;

    /**
     * @var RecordOrderBalancePlugin
     */
    private $plugin;

    /**
     * @var string[]
     */
    private $events = [];

    /**
     * @var array|null
     */
    private $added;

    protected function setUp(): void
    {
        $this->orderBalanceGuard = $this->createMock(OrderBalanceGuard::class);
        $this->orderBalanceGuard->method('add')->willReturnCallback(function (array $rows) {
            $this->events[] = 'add';
            $this->added = $rows;
        });

        $connection = $this->createMock(AdapterInterface::class);
        foreach (['beginTransaction' => 'begin', 'commit' => 'commit', 'rollBack' => 'rollback'] as $method => $event) {
            $connection->method($method)->willReturnCallback(function () use ($connection, $event) {
                $this->events[] = $event;
                return $connection;
            });
        }
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        $this->added = null;
        $this->plugin = new RecordOrderBalancePlugin(
            $this->orderBalanceGuard,
            $resource,
            new ReservationOrderIncrementId(new Json())
        );
    }

    public function testRecordsThePlacementOfAnOrder(): void
    {
        $this->invokePlugin([
            $this->reservation('000000001', -2.0, 'sku-1', 'source-a'),
            $this->reservation('000000001', -1.0, 'sku-2', null),
        ]);

        self::assertSame(['begin', 'proceed', 'add', 'commit'], $this->events);
        self::assertSame(
            [
                $this->row('000000001', 'sku-1', 'source-a', -2.0),
                $this->row('000000001', 'sku-2', '', -1.0),
            ],
            $this->added
        );
    }

    public function testRecordsTheGrantedCompensation(): void
    {
        $this->invokePlugin([$this->reservation('000000001', 3.0, 'sku-1', 'source-a')]);

        self::assertSame([$this->row('000000001', 'sku-1', 'source-a', 3.0)], $this->added);
    }

    public function testTakesTheIncrementIdFromTheMetadataOfOlderRows(): void
    {
        $reservation = $this->reservation(null, 1.0, 'sku-1', 'source-a');
        $reservation->method('getMetadata')->willReturn(
            json_encode(['object_type' => 'order', 'object_increment_id' => '000000007'])
        );

        $this->invokePlugin([$reservation]);

        self::assertSame([$this->row('000000007', 'sku-1', 'source-a', 1.0)], $this->added);
    }

    public function testLeavesTheRowsWithoutAnIncrementIdAlone(): void
    {
        $this->invokePlugin([
            $this->reservation(null, 5.0, 'sku-1', 'source-a'),
            $this->reservation('000000001', -1.0, 'sku-1', 'source-a'),
        ]);

        self::assertSame([$this->row('000000001', 'sku-1', 'source-a', -1.0)], $this->added);
    }

    public function testPassesABatchWithoutIncrementIdsThrough(): void
    {
        $this->orderBalanceGuard->expects(self::never())->method('add');

        $this->invokePlugin([$this->reservation(null, 5.0, 'sku-1', 'source-a')]);

        self::assertSame(['proceed'], $this->events);
    }

    public function testRollsBackWithoutRecordingWhenTheAppendFails(): void
    {
        $this->orderBalanceGuard->expects(self::never())->method('add');
        $failure = new \RuntimeException('append failed');

        $this->assertFailsWith($failure, function () use ($failure) {
            $this->events[] = 'proceed';
            throw $failure;
        });

        self::assertSame(['begin', 'proceed', 'rollback'], $this->events);
    }

    public function testRollsBackTheAppendWhenRecordingFails(): void
    {
        $failure = new LockWaitException('Lock wait timeout exceeded');
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('rollBack')->willReturnCallback(function () use ($connection) {
            $this->events[] = 'rollback';
            return $connection;
        });
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $guard = $this->createMock(OrderBalanceGuard::class);
        $guard->method('add')->willThrowException($failure);
        $this->plugin = new RecordOrderBalancePlugin($guard, $resource, new ReservationOrderIncrementId(new Json()));

        $this->assertFailsWith($failure, function () {
            $this->events[] = 'proceed';
        });

        self::assertSame(['proceed', 'rollback'], $this->events);
    }

    private function assertFailsWith(\Throwable $failure, callable $proceed): void
    {
        try {
            $this->plugin->aroundExecute(
                $this->createMock(AppendReservationsInterface::class),
                $proceed,
                [$this->reservation('000000001', -1.0, 'sku-1', 'source-a')]
            );
            self::fail('the failure must propagate');
        } catch (\Throwable $e) {
            self::assertSame($failure, $e);
        }
    }

    /**
     * @param ReservationInterface[] $reservations
     */
    private function invokePlugin(array $reservations): void
    {
        $proceed = function (array $appended) use ($reservations) {
            self::assertSame($reservations, $appended);
            $this->events[] = 'proceed';
        };
        $this->plugin->aroundExecute($this->createMock(AppendReservationsInterface::class), $proceed, $reservations);
    }

    /**
     * @return ReservationInterface|MockObject
     */
    private function reservation(?string $incrementId, float $qty, string $sku, ?string $source): ReservationInterface
    {
        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn($qty);
        $reservation->method('getSku')->willReturn($sku);
        $reservation->method('getStockId')->willReturn(self::STOCK_ID);
        $reservation->method('getSourceCode')->willReturn($source);
        $reservation->method('getObjectIncrementId')->willReturn($incrementId);

        return $reservation;
    }

    private function row(string $incrementId, string $sku, string $source, float $quantity): array
    {
        return [
            'object_increment_id' => $incrementId,
            'stock_id' => self::STOCK_ID,
            'sku' => $sku,
            'source_code' => $source,
            'quantity' => $quantity,
        ];
    }
}
