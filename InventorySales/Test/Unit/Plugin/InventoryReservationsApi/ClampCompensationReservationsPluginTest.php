<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryReservationsApi;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationBalance;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderBalanceGuard;
use Magento\InventorySales\Model\SourceReservation\ReservationOrderIncrementId;
use Magento\InventorySales\Plugin\InventoryReservationsApi\ClampCompensationReservationsPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ClampCompensationReservationsPluginTest extends TestCase
{
    private const STOCK_ID = 10;
    private const INCREMENT_ID = '000000123';

    /**
     * @var GetOrderReservationBalance|MockObject
     */
    private $getOrderReservationBalance;

    /**
     * @var ReservationBuilderInterface|MockObject
     */
    private $reservationBuilder;

    /**
     * @var OrderBalanceGuard|MockObject
     */
    private $orderBalanceGuard;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ClampCompensationReservationsPlugin
     */
    private $plugin;

    /**
     * @var ReservationInterface[]|null
     */
    private $appended;

    /**
     * @var string[]
     */
    private $events = [];

    /**
     * @var array
     */
    private $committed = [];

    protected function setUp(): void
    {
        $this->getOrderReservationBalance = $this->createMock(GetOrderReservationBalance::class);
        $this->reservationBuilder = $this->createMock(ReservationBuilderInterface::class);
        $this->reservationBuilder->method('setSku')->willReturnSelf();
        $this->reservationBuilder->method('setQuantity')->willReturnSelf();
        $this->reservationBuilder->method('setStockId')->willReturnSelf();
        $this->reservationBuilder->method('setMetadata')->willReturnSelf();
        $this->reservationBuilder->method('setSourceCode')->willReturnSelf();
        $this->reservationBuilder->method('setObjectIncrementId')->willReturnSelf();
        $this->reservationBuilder->method('build')
            ->willReturn($this->createMock(ReservationInterface::class));

        $this->orderBalanceGuard = $this->createMock(OrderBalanceGuard::class);
        $this->orderBalanceGuard->method('lock')->willReturnCallback(function (array $groups) {
            $this->events[] = 'lock';
            $balances = [];
            foreach ($groups as $key => $group) {
                $row = $group['sku'] . '@' . $group['source_code'];
                $balances[$key] = array_key_exists($row, $this->committed) ? $this->committed[$row] : $group['ledger'];
            }
            return $balances;
        });

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('beginTransaction')->willReturnCallback(function () use ($connection) {
            $this->events[] = 'begin';
            return $connection;
        });
        $connection->method('commit')->willReturnCallback(function () use ($connection) {
            $this->events[] = 'commit';
            return $connection;
        });
        $connection->method('rollBack')->willReturnCallback(function () use ($connection) {
            $this->events[] = 'rollback';
            return $connection;
        });
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->appended = null;

        $this->plugin = new ClampCompensationReservationsPlugin(
            $this->getOrderReservationBalance,
            $this->reservationBuilder,
            $this->logger,
            $this->orderBalanceGuard,
            $resource,
            new ReservationOrderIncrementId(new Json())
        );
    }

    public function testPassesThroughDemandReservations(): void
    {
        $this->getOrderReservationBalance->expects(self::never())->method('execute');
        $this->orderBalanceGuard->expects(self::never())->method('lock');

        $reservations = [$this->reservation(-5.0, 'sku-1', 'source-a')];
        $this->invokePlugin($reservations);

        self::assertSame($reservations, $this->appended);
        self::assertSame(['proceed'], $this->events);
    }

    public function testAllowsReleaseWithinOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0]]);
        $this->reservationBuilder->expects(self::never())->method('build');

        $reservations = [$this->reservation(5.0, 'sku-1', 'source-a')];
        $this->invokePlugin($reservations);

        self::assertSame($reservations, $this->appended);
    }

    public function testClampsReleaseExceedingOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0]]);
        $this->reservationBuilder->expects(self::once())->method('setQuantity')->with(5.0)->willReturnSelf();

        $this->invokePlugin([$this->reservation(8.0, 'sku-1', 'source-a')]);

        self::assertCount(1, $this->appended);
    }

    public function testDropsReleaseWithNoOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => 0.0]]);
        $this->reservationBuilder->expects(self::never())->method('build');

        $this->invokePlugin([$this->reservation(5.0, 'sku-1', 'source-a')]);

        self::assertNull($this->appended);
        self::assertSame(['begin', 'lock', 'commit'], $this->events);
    }

    public function testMultipleReleasesShareTheOutstandingBalance(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0]]);
        $this->reservationBuilder->expects(self::once())->method('setQuantity')->with(2.0)->willReturnSelf();

        $this->invokePlugin([
            $this->reservation(3.0, 'sku-1', 'source-a'),
            $this->reservation(3.0, 'sku-1', 'source-a'),
        ]);

        self::assertCount(2, $this->appended);
    }

    public function testPassesThroughWhenMetadataHasNoOrderContext(): void
    {
        $this->getOrderReservationBalance->expects(self::never())->method('execute');
        $this->orderBalanceGuard->expects(self::never())->method('lock');

        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn(5.0);
        $reservation->method('getMetadata')->willReturn('');
        $reservations = [$reservation];
        $this->invokePlugin($reservations);

        self::assertSame($reservations, $this->appended);
    }

    public function testClampsAgainstStockScopedBalanceForNullSource(): void
    {
        $this->givenBalance(['sku-1' => ['' => -4.0]]);
        $this->reservationBuilder->expects(self::once())->method('setQuantity')->with(4.0)->willReturnSelf();

        $this->invokePlugin([$this->reservation(6.0, 'sku-1', null)]);

        self::assertCount(1, $this->appended);
    }

    public function testLocksEveryReleasedGroupWithItsLedger(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0, '' => -1.0]]);
        $this->expectLockOf([$this->group('sku-1', 'source-a', -5.0), $this->group('sku-1', '', -1.0)]);

        $this->invokePlugin([
            $this->reservation(1.0, 'sku-1', 'source-a'),
            $this->reservation(1.0, 'sku-1', null),
            $this->reservation(1.0, 'sku-1', 'source-a'),
        ]);

        self::assertCount(3, $this->appended);
    }

    public function testLocksTheDemandGroupsOfABatchThatCarriesARelease(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -3.0, 'source-b' => -2.0]]);
        $this->expectLockOf([$this->group('sku-1', 'source-a', -3.0), $this->group('sku-1', 'source-b', -2.0)]);

        $reservations = [$this->reservation(3.0, 'sku-1', 'source-a'), $this->reservation(-3.0, 'sku-1', 'source-b')];
        $this->invokePlugin($reservations);

        self::assertSame($reservations, $this->appended);
    }

    public function testFailsATransferWhenItsReleasedGroupStaysLocked(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -3.0, 'source-b' => -2.0]]);
        $this->givenCommitted('sku-1', 'source-a', null);

        $this->assertTransferFails();
    }

    public function testFailsATransferWhenItsDemandGroupStaysLocked(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -3.0, 'source-b' => -2.0]]);
        $this->givenCommitted('sku-1', 'source-b', null);

        $this->assertTransferFails();
    }

    public function testClampsToTheCommittedBalanceWhenItIsBelowTheLedger(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -5.0]]);
        $this->givenCommitted('sku-1', 'source-a', -2.0);
        $this->reservationBuilder->expects(self::once())->method('setQuantity')->with(2.0)->willReturnSelf();

        $this->invokePlugin([$this->reservation(5.0, 'sku-1', 'source-a')]);

        self::assertCount(1, $this->appended);
    }

    public function testClampsToTheLedgerWhenItIsBelowTheCommittedBalance(): void
    {
        $this->givenBalance([]);
        $this->givenCommitted('sku-1', 'source-a', -1.0);
        $this->reservationBuilder->expects(self::never())->method('build');

        $this->invokePlugin([$this->reservation(1.0, 'sku-1', 'source-a')]);

        self::assertNull($this->appended);
    }

    public function testDropsTheReleasesOfAGroupThatStaysLockedAndLogsAnError(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -1.0], 'sku-2' => ['source-a' => -1.0]]);
        $this->givenCommitted('sku-1', 'source-a', null);
        $locked = $this->reservation(1.0, 'sku-1', 'source-a');
        $free = $this->reservation(1.0, 'sku-2', 'source-a');
        $this->logger->expects(self::once())->method('error')->with(
            self::stringContains('not written'),
            self::callback(static fn (array $context) => $context['object_increment_id'] === self::INCREMENT_ID
                && $context['sku'] === 'sku-1'
                && $context['source_code'] === 'source-a'
                && $context['requested'] === 1.0
                && is_float($context['waited_seconds']))
        );

        $this->invokePlugin([$locked, $free]);

        self::assertSame([$free], $this->appended);
    }

    public function testWritesInsideOneTransaction(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -1.0]]);

        $this->invokePlugin([$this->reservation(1.0, 'sku-1', 'source-a')]);

        self::assertSame(['begin', 'lock', 'proceed', 'commit'], $this->events);
    }

    public function testRollsBackWhenTheAppendFails(): void
    {
        $this->givenBalance(['sku-1' => ['source-a' => -1.0]]);
        $failure = new \RuntimeException('append failed');

        try {
            $this->plugin->aroundExecute(
                $this->createMock(AppendReservationsInterface::class),
                function () use ($failure) {
                    $this->events[] = 'proceed';
                    throw $failure;
                },
                [$this->reservation(1.0, 'sku-1', 'source-a')]
            );
            self::fail('the failure must propagate');
        } catch (\RuntimeException $e) {
            self::assertSame($failure, $e);
        }

        self::assertSame(['begin', 'lock', 'proceed', 'rollback'], $this->events);
    }

    /**
     * @param array<string, array<string, float>> $balance
     */
    private function givenBalance(array $balance): void
    {
        $this->getOrderReservationBalance->method('execute')->willReturn($balance);
    }

    private function givenCommitted(string $sku, string $source, ?float $balance): void
    {
        $this->committed[$sku . '@' . $source] = $balance;
    }

    private function expectLockOf(array $groups): void
    {
        $this->orderBalanceGuard->expects(self::once())
            ->method('lock')
            ->with(self::callback(static fn (array $locked) => array_values($locked) === $groups))
            ->willReturnCallback(
                static fn (array $locked) => array_map(static fn (array $group) => $group['ledger'], $locked)
            );
    }

    private function assertTransferFails(): void
    {
        try {
            $this->invokePlugin([
                $this->reservation(3.0, 'sku-1', 'source-a'),
                $this->reservation(-3.0, 'sku-1', 'source-b'),
            ]);
            self::fail('the transfer must fail');
        } catch (CouldNotSaveException $e) {
            self::assertStringContainsString(self::INCREMENT_ID, $e->getMessage());
        }

        self::assertNull($this->appended);
        self::assertSame(['begin', 'lock', 'rollback'], $this->events);
    }

    private function group(string $sku, string $source, float $ledger): array
    {
        return [
            'object_increment_id' => self::INCREMENT_ID,
            'stock_id' => self::STOCK_ID,
            'sku' => $sku,
            'source_code' => $source,
            'ledger' => $ledger,
        ];
    }

    /**
     * @param ReservationInterface[] $reservations
     */
    private function invokePlugin(array $reservations): void
    {
        $proceed = function (array $appended) {
            $this->events[] = 'proceed';
            $this->appended = $appended;
        };
        $this->plugin->aroundExecute($this->createMock(AppendReservationsInterface::class), $proceed, $reservations);
    }

    private function reservation(float $qty, string $sku, ?string $source): ReservationInterface
    {
        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn($qty);
        $reservation->method('getSku')->willReturn($sku);
        $reservation->method('getStockId')->willReturn(self::STOCK_ID);
        $reservation->method('getSourceCode')->willReturn($source);
        $reservation->method('getObjectIncrementId')->willReturn(self::INCREMENT_ID);
        $reservation->method('getMetadata')->willReturn(
            json_encode(['object_type' => 'order', 'object_id' => '123', 'object_increment_id' => self::INCREMENT_ID])
        );

        return $reservation;
    }
}
