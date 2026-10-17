<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model;

use Magento\InventorySales\Model\GetSkusRequiringReservation;
use Magento\InventorySales\Model\ReservationLock;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReservationLockTest extends TestCase
{
    /**
     * @var GetSkusRequiringReservation|MockObject
     */
    private $getSkusRequiringReservation;

    /**
     * @var AcquireStockItemLocks|MockObject
     */
    private $locks;

    /**
     * @var ReservationLock
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->getSkusRequiringReservation = $this->createMock(GetSkusRequiringReservation::class);
        $this->locks = $this->createMock(AcquireStockItemLocks::class);
        $this->model = new ReservationLock($this->getSkusRequiringReservation, $this->locks);
    }

    public function testLocksOnlyTheSkusThatGetAReservation(): void
    {
        $this->getSkusRequiringReservation->method('execute')->with(['lk_s', 'lk_free'], 5)->willReturn(['lk_s']);
        $this->locks->expects($this->once())->method('execute')->with(['lk_s'], 5)->willReturn(['lock_lk_s']);

        $this->assertSame(['lock_lk_s'], $this->model->acquire(['lk_s', 'lk_free'], 5));
    }

    public function testTakesNoLockWhenNothingIsReserved(): void
    {
        $this->getSkusRequiringReservation->method('execute')->willReturn([]);
        $this->locks->expects($this->never())->method('execute');

        $this->assertSame([], $this->model->acquire(['lk_free'], 5));
    }

    public function testReleasesExactlyTheLocksItTook(): void
    {
        $this->locks->expects($this->once())->method('release')->with(['lock_lk_s']);

        $this->model->release(['lock_lk_s']);
    }

    public function testReleasesNothingWhenNoLockWasTaken(): void
    {
        $this->locks->expects($this->never())->method('release');

        $this->model->release([]);
    }
}
