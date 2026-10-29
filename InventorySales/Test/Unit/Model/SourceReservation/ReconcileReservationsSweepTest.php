<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetAbandonedPlacements;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetTerminalOrdersWithResidualReservations;
use Magento\InventorySales\Model\SourceReservation\AbandonedPlacementMark;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReconcileReservationsSweepTest extends TestCase
{
    /**
     * @var GetTerminalOrdersWithResidualReservations|MockObject
     */
    private $getTerminalOrders;

    /**
     * @var ReconcileOrderReservations|MockObject
     */
    private $engine;

    /**
     * @var GetAbandonedPlacements|MockObject
     */
    private $getAbandonedPlacements;

    /**
     * @var AbandonedPlacementMark|MockObject
     */
    private $mark;

    /**
     * @var LoggerInterface|MockObject
     */
    private $logger;

    /**
     * @var ReconcileReservationsSweep
     */
    private $sweep;

    protected function setUp(): void
    {
        $this->getTerminalOrders = $this->createMock(GetTerminalOrdersWithResidualReservations::class);
        $this->engine = $this->createMock(ReconcileOrderReservations::class);
        $this->getAbandonedPlacements = $this->createMock(GetAbandonedPlacements::class);
        $this->mark = $this->createMock(AbandonedPlacementMark::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sweep = new ReconcileReservationsSweep(
            $this->getTerminalOrders,
            $this->engine,
            $this->getAbandonedPlacements,
            $this->mark,
            $this->logger
        );
    }

    public function testReconcilesEachOrderAndReportsTheAffectedStocks(): void
    {
        $this->getTerminalOrders->method('execute')->willReturn([
            ['object_id' => 1, 'increment_id' => '000000001', 'state' => Order::STATE_COMPLETE],
            ['object_id' => 2, 'increment_id' => '000000002', 'state' => Order::STATE_CANCELED],
        ]);
        $this->engine->method('execute')->willReturnOnConsecutiveCalls(
            [['stock_id' => 2, 'sku' => 'A', 'source_code' => 'slr_a', 'quantity' => 3.0]],
            [['stock_id' => 3, 'sku' => 'A', 'source_code' => 'slr_a', 'quantity' => 1.0]]
        );

        $result = $this->sweep->execute(500);

        self::assertSame(2, $result['orders']);
        self::assertSame(2, $result['compensations']);
        self::assertSame([2, 3], $result['stock_ids']);
        self::assertFalse($result['limit_reached']);
    }

    public function testDryRunReportsTheCompensationsItWouldMake(): void
    {
        $this->getTerminalOrders->method('execute')->willReturn([
            ['object_id' => 1, 'increment_id' => '000000001', 'state' => Order::STATE_COMPLETE],
        ]);
        $this->engine->expects(self::once())->method('execute')
            ->with(1, '000000001', Order::STATE_COMPLETE, true)
            ->willReturn([['stock_id' => 2, 'sku' => 'A', 'source_code' => null, 'quantity' => 4.0]]);

        $result = $this->sweep->execute(500, true);

        self::assertSame(1, $result['compensations']);
    }

    public function testLimitReachedWhenBatchIsFull(): void
    {
        $this->getTerminalOrders->method('execute')->willReturn([
            ['object_id' => 1, 'increment_id' => '000000001', 'state' => Order::STATE_COMPLETE],
        ]);
        $this->engine->method('execute')->willReturn([]);

        $result = $this->sweep->execute(1);

        self::assertTrue($result['limit_reached']);
    }

    public function testRepairsAbandonedPlacementsBelowAnEffectiveMarkAndAdvancesIt(): void
    {
        $this->mark->method('current')->willReturn(900);
        $this->getAbandonedPlacements->method('execute')->willReturn([
            ['increment_id' => '000000007', 'last_id' => 100],
            ['increment_id' => '000000008', 'last_id' => 900],
            ['increment_id' => '000000009', 'last_id' => 901],
        ]);
        $calls = [];
        $this->engine->expects(self::exactly(2))->method('executeAbandoned')->willReturnCallback(
            function (string $incrementId, int $cutoff, bool $dryRun) use (&$calls) {
                $calls[] = [$incrementId, $cutoff, $dryRun];
                return $incrementId === '000000007'
                    ? [['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'quantity' => 1.0]]
                    : [
                        ['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'quantity' => 1.0],
                        ['stock_id' => 6, 'sku' => 'lk_s', 'source_code' => 'slr_b', 'quantity' => 1.0],
                    ];
            }
        );
        $this->mark->expects(self::once())->method('advance');

        $result = $this->sweep->execute(500);

        self::assertSame([['000000007', 900, false], ['000000008', 900, false]], $calls);
        self::assertSame(0, $result['orders']);
        self::assertSame(2, $result['abandoned']);
        self::assertSame(1, $result['abandoned_pending']);
        self::assertTrue($result['mark_effective']);
        self::assertSame(3, $result['compensations']);
        self::assertSame([5, 6], $result['stock_ids']);
        self::assertFalse($result['limit_reached']);
    }

    public function testWithoutAnEffectiveMarkReportsAbandonedPlacementsAsPending(): void
    {
        $this->mark->method('current')->willReturn(null);
        $this->getAbandonedPlacements->method('execute')->willReturn([
            ['increment_id' => '000000007', 'last_id' => 100],
            ['increment_id' => '000000008', 'last_id' => 200],
        ]);
        $this->engine->expects(self::never())->method('executeAbandoned');
        $this->mark->expects(self::once())->method('advance');

        $result = $this->sweep->execute(500);

        self::assertSame(0, $result['abandoned']);
        self::assertSame(2, $result['abandoned_pending']);
        self::assertFalse($result['mark_effective']);
    }

    public function testDryRunReportsAbandonedPlacementsWithoutWritingOrAdvancingTheMark(): void
    {
        $this->mark->method('current')->willReturn(900);
        $this->getAbandonedPlacements->method('execute')->willReturn([
            ['increment_id' => '000000007', 'last_id' => 100],
        ]);
        $this->engine->expects(self::once())->method('executeAbandoned')->with('000000007', 900, true)
            ->willReturn([['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'quantity' => 1.0]]);
        $this->mark->expects(self::never())->method('advance');

        $result = $this->sweep->execute(500, true);

        self::assertSame(1, $result['abandoned']);
        self::assertSame(0, $result['abandoned_pending']);
    }

    public function testTerminalOrdersAndAbandonedPlacementsAreCountedApart(): void
    {
        $this->getTerminalOrders->method('execute')->willReturn([
            ['object_id' => 1, 'increment_id' => '000000001', 'state' => Order::STATE_COMPLETE],
        ]);
        $this->engine->method('execute')->willReturn(
            [['stock_id' => 2, 'sku' => 'A', 'source_code' => 'slr_a', 'quantity' => 3.0]]
        );
        $this->mark->method('current')->willReturn(900);
        $this->getAbandonedPlacements->method('execute')->willReturn([
            ['increment_id' => '000000007', 'last_id' => 100],
        ]);
        $this->engine->method('executeAbandoned')->willReturn(
            [['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'quantity' => 1.0]]
        );

        $result = $this->sweep->execute(500);

        self::assertSame(1, $result['orders']);
        self::assertSame(1, $result['abandoned']);
        self::assertSame(2, $result['compensations']);
    }

    public function testPlacementsBeyondTheLimitAreLeftForTheNextRunAndNotReportedAsTooRecent(): void
    {
        $this->mark->method('current')->willReturn(900);
        $this->getAbandonedPlacements->method('execute')->willReturn([
            ['increment_id' => '000000007', 'last_id' => 100],
            ['increment_id' => '000000008', 'last_id' => 200],
        ]);
        $this->engine->expects(self::once())->method('executeAbandoned')->with('000000007')->willReturn([]);

        $result = $this->sweep->execute(1);

        self::assertTrue($result['limit_reached']);
        self::assertSame(0, $result['abandoned_pending']);
    }

    public function testAPlacementThatFailsToReleaseIsLoggedAndSkipped(): void
    {
        $this->mark->method('current')->willReturn(900);
        $this->getAbandonedPlacements->method('execute')->willReturn([
            ['increment_id' => '000000007', 'last_id' => 100],
            ['increment_id' => '000000008', 'last_id' => 200],
        ]);
        $this->engine->method('executeAbandoned')->willReturnCallback(
            function (string $incrementId) {
                if ($incrementId === '000000007') {
                    throw new \RuntimeException('sku no longer exists');
                }
                return [['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'quantity' => 1.0]];
            }
        );
        $this->logger->expects(self::once())->method('warning')->with(
            self::stringContains('abandoned placement'),
            ['increment_id' => '000000007', 'exception' => 'sku no longer exists']
        );
        $this->mark->expects(self::once())->method('advance');

        $result = $this->sweep->execute(500);

        self::assertSame(1, $result['abandoned']);
        self::assertSame(1, $result['compensations']);
    }
}
