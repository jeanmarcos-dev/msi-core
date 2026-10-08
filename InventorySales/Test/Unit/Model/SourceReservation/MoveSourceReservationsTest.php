<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetPendingReservationsAtSource;
use Magento\InventorySales\Model\SourceReservation\MoveSourceReservations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MoveSourceReservationsTest extends TestCase
{
    /**
     * @var GetPendingReservationsAtSource|MockObject
     */
    private $getPending;

    /**
     * @var AppendReservationsInterface|MockObject
     */
    private $appendReservations;

    /**
     * @var array
     */
    private $built = [];

    /**
     * @var MoveSourceReservations
     */
    private $model;

    protected function setUp(): void
    {
        $this->getPending = $this->createMock(GetPendingReservationsAtSource::class);
        $this->appendReservations = $this->createMock(AppendReservationsInterface::class);
        $getSources = $this->createMock(GetSourcesAssignedToStockOrderedByPriorityInterface::class);
        $getSources->method('execute')->willReturnCallback(function (int $stockId) {
            $codes = $stockId === 5 ? ['slr_a', 'slr_b'] : ['slr_a'];
            return array_map(function (string $code) {
                $source = $this->createMock(SourceInterface::class);
                $source->method('getSourceCode')->willReturn($code);
                return $source;
            }, $codes);
        });

        $this->model = new MoveSourceReservations(
            $this->getPending,
            $getSources,
            $this->appendReservations,
            $this->reservationBuilder(),
            new Json()
        );
    }

    public function testMovesEveryPendingReservationOfTheOriginToTheDestination(): void
    {
        $this->getPending->method('execute')->with(['SLR-1'], 'slr_a')->willReturn([
            ['stock_id' => 5, 'sku' => 'SLR-1', 'object_increment_id' => '000000042', 'object_id' => '42',
                'quantity' => -3.0],
            ['stock_id' => 5, 'sku' => 'SLR-1', 'object_increment_id' => '000000043', 'object_id' => '43',
                'quantity' => -1.0],
        ]);
        $this->appendReservations->expects(self::once())->method('execute');

        $this->model->execute(['SLR-1'], 'slr_a', 'slr_b');

        self::assertSame(
            [
                [5, 'SLR-1', 'slr_a', 3.0, '000000042'],
                [5, 'SLR-1', 'slr_b', -3.0, '000000042'],
                [5, 'SLR-1', 'slr_a', 1.0, '000000043'],
                [5, 'SLR-1', 'slr_b', -1.0, '000000043'],
            ],
            array_map(fn (array $row) => array_slice($row, 0, 5), $this->built)
        );
        self::assertSame(
            ['event_type' => 'source_transfer', 'object_type' => 'order', 'object_id' => '42',
                'object_increment_id' => '000000042'],
            json_decode($this->built[0][5], true)
        );
    }

    public function testRefusesWhenTheDestinationIsOutsideTheStockOfAnOrder(): void
    {
        $this->getPending->method('execute')->willReturn([
            ['stock_id' => 6, 'sku' => 'SLR-1', 'object_increment_id' => '000000042', 'object_id' => '42',
                'quantity' => -3.0],
        ]);
        $this->appendReservations->expects(self::never())->method('execute');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(
            'Source slr_b is not assigned to stock 6, which holds reservations at slr_a for order 000000042.'
        );

        $this->model->execute(['SLR-1'], 'slr_a', 'slr_b');
    }

    public function testDoesNothingWithoutPendingReservations(): void
    {
        $this->getPending->method('execute')->willReturn([]);
        $this->appendReservations->expects(self::never())->method('execute');

        $this->model->execute(['SLR-1'], 'slr_a', 'slr_b');
    }

    private function reservationBuilder(): ReservationBuilderInterface
    {
        $state = [];
        $builder = $this->createMock(ReservationBuilderInterface::class);
        $setters = ['setStockId', 'setSku', 'setSourceCode', 'setQuantity', 'setObjectIncrementId', 'setMetadata'];
        foreach ($setters as $setter) {
            $builder->method($setter)->willReturnCallback(function ($value) use (&$state, $setter, $builder) {
                $state[$setter] = $value;
                return $builder;
            });
        }
        $builder->method('build')->willReturnCallback(function () use (&$state) {
            $this->built[] = [
                $state['setStockId'],
                $state['setSku'],
                $state['setSourceCode'],
                $state['setQuantity'],
                $state['setObjectIncrementId'],
                $state['setMetadata'],
            ];
            $state = [];
            return $this->createMock(ReservationInterface::class);
        });
        return $builder;
    }
}
