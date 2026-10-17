<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationLedger;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderExistsByIncrementId;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReconcileOrderReservationsTest extends TestCase
{
    private const OBJECT_ID = 42;
    private const INCREMENT_ID = '000000042';

    /**
     * @var GetOrderReservationLedger|MockObject
     */
    private $getOrderReservationLedger;

    /**
     * @var AppendReservationsInterface|MockObject
     */
    private $appendReservations;

    /**
     * @var OrderExistsByIncrementId|MockObject
     */
    private $orderExists;

    /**
     * @var string[]
     */
    private array $metadata = [];

    /**
     * @var ReconcileOrderReservations
     */
    private $model;

    protected function setUp(): void
    {
        $this->getOrderReservationLedger = $this->createMock(GetOrderReservationLedger::class);
        $this->appendReservations = $this->createMock(AppendReservationsInterface::class);

        $reservationBuilder = $this->createMock(ReservationBuilderInterface::class);
        $reservationBuilder->method('setSku')->willReturnSelf();
        $reservationBuilder->method('setQuantity')->willReturnSelf();
        $reservationBuilder->method('setStockId')->willReturnSelf();
        $reservationBuilder->method('setMetadata')->willReturnCallback(
            function (string $metadata) use ($reservationBuilder) {
                $this->metadata[] = $metadata;
                return $reservationBuilder;
            }
        );
        $reservationBuilder->method('setSourceCode')->willReturnSelf();
        $reservationBuilder->method('setObjectIncrementId')->willReturnSelf();
        $reservationBuilder->method('build')->willReturnCallback(
            fn () => $this->createMock(ReservationInterface::class)
        );

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('serialize')->willReturnCallback(static fn ($value) => json_encode($value));

        $this->orderExists = $this->createMock(OrderExistsByIncrementId::class);

        $this->model = new ReconcileOrderReservations(
            $this->getOrderReservationLedger,
            $this->appendReservations,
            $reservationBuilder,
            $serializer,
            $this->orderExists
        );
    }

    public function testSkipsNonTerminalOrder(): void
    {
        $this->getOrderReservationLedger->expects(self::never())->method('execute');
        $this->appendReservations->expects(self::never())->method('execute');

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, 'processing');

        self::assertSame([], $result);
    }

    public function testSkipsWhenNoNegativeBalance(): void
    {
        $this->getOrderReservationLedger->method('execute')->willReturn([]);
        $this->appendReservations->expects(self::never())->method('execute');

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, Order::STATE_COMPLETE);

        self::assertSame([], $result);
    }

    public function testReleasesNegativeBalancePerSource(): void
    {
        $this->getOrderReservationLedger->method('execute')->with(self::INCREMENT_ID, null)->willReturn([
            ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_a', 'balance' => -3.0],
            ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_b', 'balance' => -2.0],
        ]);
        $this->appendReservations->expects(self::once())->method('execute')
            ->with(self::countOf(2));

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, Order::STATE_CANCELED);

        self::assertSame(
            [
                ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_a', 'quantity' => 3.0],
                ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => 'slr_b', 'quantity' => 2.0],
            ],
            $result
        );
    }

    public function testDryRunPlansWithoutAppending(): void
    {
        $this->getOrderReservationLedger->method('execute')->willReturn([
            ['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => null, 'balance' => -4.0],
        ]);
        $this->appendReservations->expects(self::never())->method('execute');

        $result = $this->model->execute(self::OBJECT_ID, self::INCREMENT_ID, Order::STATE_CLOSED, true);

        self::assertSame(
            [['stock_id' => 2, 'sku' => 'SLR-1', 'source_code' => null, 'quantity' => 4.0]],
            $result
        );
    }

    public function testSkipsEmptyIncrementId(): void
    {
        $this->getOrderReservationLedger->expects(self::never())->method('execute');

        self::assertSame([], $this->model->execute(self::OBJECT_ID, '', Order::STATE_COMPLETE));
    }

    public function testReleasesAnAbandonedPlacementAsItsOwnEvent(): void
    {
        $this->orderExists->method('execute')->with(self::INCREMENT_ID)->willReturn(false);
        $this->getOrderReservationLedger->method('execute')->with(self::INCREMENT_ID, 900)->willReturn([
            ['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'balance' => -1.0],
            ['stock_id' => 5, 'sku' => 'lk_s2', 'source_code' => null, 'balance' => -2.0],
        ]);
        $this->appendReservations->expects(self::once())->method('execute')->with(self::countOf(2));

        $result = $this->model->executeAbandoned(self::INCREMENT_ID, 900);

        self::assertSame(
            [
                ['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'quantity' => 1.0],
                ['stock_id' => 5, 'sku' => 'lk_s2', 'source_code' => null, 'quantity' => 2.0],
            ],
            $result
        );
        self::assertSame(
            [
                'event_type' => 'abandoned_placement',
                'object_type' => 'order',
                'object_id' => '',
                'object_increment_id' => self::INCREMENT_ID,
            ],
            json_decode($this->metadata[0], true)
        );
    }

    public function testDryRunPlansAnAbandonedPlacementWithoutAppending(): void
    {
        $this->orderExists->method('execute')->willReturn(false);
        $this->getOrderReservationLedger->method('execute')->willReturn([
            ['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'balance' => -1.0],
        ]);
        $this->appendReservations->expects(self::never())->method('execute');

        self::assertSame(
            [['stock_id' => 5, 'sku' => 'lk_s', 'source_code' => 'slr_a', 'quantity' => 1.0]],
            $this->model->executeAbandoned(self::INCREMENT_ID, 900, true)
        );
    }

    public function testLeavesAPlacementAloneOnceItsOrderExists(): void
    {
        $this->orderExists->method('execute')->with(self::INCREMENT_ID)->willReturn(true);
        $this->getOrderReservationLedger->expects(self::never())->method('execute');
        $this->appendReservations->expects(self::never())->method('execute');

        self::assertSame([], $this->model->executeAbandoned(self::INCREMENT_ID, 900));
    }

    public function testSkipsAnAbandonedPlacementWithoutIncrementId(): void
    {
        $this->orderExists->expects(self::never())->method('execute');
        $this->getOrderReservationLedger->expects(self::never())->method('execute');

        self::assertSame([], $this->model->executeAbandoned('', 900));
    }
}
