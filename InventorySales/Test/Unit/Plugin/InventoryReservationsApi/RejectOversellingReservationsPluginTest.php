<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Plugin\InventoryReservationsApi;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySalesApi\Api\AreProductsSalableForRequestedQtyInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableForRequestedQtyRequestInterface;
use Magento\InventorySalesApi\Api\Data\IsProductSalableForRequestedQtyRequestInterfaceFactory;
use Magento\InventorySalesApi\Api\Data\IsProductSalableForRequestedQtyResultInterface;
use Magento\InventorySales\Plugin\InventoryReservationsApi\RejectOversellingReservationsPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RejectOversellingReservationsPluginTest extends TestCase
{
    private const STOCK_ID = 10;

    /**
     * @var AreProductsSalableForRequestedQtyInterface|MockObject
     */
    private $areProductsSalableForRequestedQty;

    /**
     * @var IsProductSalableForRequestedQtyRequestInterfaceFactory|MockObject
     */
    private $requestFactory;

    /**
     * @var AppendReservationsInterface|MockObject
     */
    private $subject;

    /**
     * @var RejectOversellingReservationsPlugin
     */
    private $plugin;

    /**
     * @var bool
     */
    private $proceedCalled;

    /**
     * @var array<int,float>
     */
    private $requestedQties;

    protected function setUp(): void
    {
        $this->areProductsSalableForRequestedQty = $this->createMock(
            AreProductsSalableForRequestedQtyInterface::class
        );
        $this->requestFactory = $this->createMock(
            IsProductSalableForRequestedQtyRequestInterfaceFactory::class
        );
        $this->requestedQties = [];
        $this->requestFactory->method('create')->willReturnCallback(
            function (array $data): IsProductSalableForRequestedQtyRequestInterface {
                $this->requestedQties[] = $data['qty'];
                $request = $this->createMock(IsProductSalableForRequestedQtyRequestInterface::class);
                $request->method('getSku')->willReturn((string)$data['sku']);
                $request->method('getQty')->willReturn((float)$data['qty']);

                return $request;
            }
        );

        $this->subject = $this->createMock(AppendReservationsInterface::class);
        $this->proceedCalled = false;

        $this->plugin = new RejectOversellingReservationsPlugin(
            $this->areProductsSalableForRequestedQty,
            $this->requestFactory
        );
    }

    public function testAllowsSalableDemand(): void
    {
        $this->givenSalability(['sku-1' => true]);

        $this->invokePlugin([$this->reservation(-5.0, 'sku-1')]);

        self::assertTrue($this->proceedCalled);
    }

    public function testRejectsNonSalableDemand(): void
    {
        $this->givenSalability(['sku-1' => false]);

        $this->expectException(CouldNotSaveException::class);
        try {
            $this->invokePlugin([$this->reservation(-5.0, 'sku-1')]);
        } finally {
            self::assertFalse($this->proceedCalled);
        }
    }

    public function testIgnoresCompensationReservations(): void
    {
        $this->areProductsSalableForRequestedQty->expects(self::never())->method('execute');

        $this->invokePlugin([$this->reservation(5.0, 'sku-1')]);

        self::assertTrue($this->proceedCalled);
    }

    public function testAggregatesDemandPerSku(): void
    {
        $this->givenSalability(['sku-1' => true]);

        $this->invokePlugin([
            $this->reservation(-3.0, 'sku-1'),
            $this->reservation(-2.0, 'sku-1'),
        ]);

        self::assertSame([5.0], $this->requestedQties);
    }

    public function testIgnoresABatchThatMovesReservationsBetweenSources(): void
    {
        $this->areProductsSalableForRequestedQty->expects(self::never())->method('execute');

        $this->invokePlugin([
            $this->reservation(3.0, 'sku-1'),
            $this->reservation(-3.0, 'sku-1'),
        ]);

        self::assertTrue($this->proceedCalled);
    }

    public function testChecksOnlyTheNetDemandOfTheBatch(): void
    {
        $this->givenSalability(['sku-1' => true]);

        $this->invokePlugin([
            $this->reservation(-5.0, 'sku-1'),
            $this->reservation(2.0, 'sku-1'),
        ]);

        self::assertSame([3.0], $this->requestedQties);
    }

    public function testChecksEachStockSeparately(): void
    {
        $this->areProductsSalableForRequestedQty->expects(self::exactly(2))
            ->method('execute')
            ->willReturn([$this->salableResult('sku-1', true)]);

        $this->invokePlugin([
            $this->reservation(-1.0, 'sku-1', 10),
            $this->reservation(-1.0, 'sku-1', 20),
        ]);

        self::assertTrue($this->proceedCalled);
    }

    /**
     * @param array<string,bool> $salableBySku
     */
    private function givenSalability(array $salableBySku): void
    {
        $results = [];
        foreach ($salableBySku as $sku => $isSalable) {
            $results[] = $this->salableResult((string)$sku, $isSalable);
        }
        $this->areProductsSalableForRequestedQty->method('execute')->willReturn($results);
    }

    private function salableResult(string $sku, bool $isSalable): IsProductSalableForRequestedQtyResultInterface
    {
        $result = $this->createMock(IsProductSalableForRequestedQtyResultInterface::class);
        $result->method('getSku')->willReturn($sku);
        $result->method('isSalable')->willReturn($isSalable);

        return $result;
    }

    private function reservation(float $qty, string $sku, int $stockId = self::STOCK_ID): ReservationInterface
    {
        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn($qty);
        $reservation->method('getSku')->willReturn($sku);
        $reservation->method('getStockId')->willReturn($stockId);

        return $reservation;
    }

    /**
     * @param ReservationInterface[] $reservations
     */
    private function invokePlugin(array $reservations): void
    {
        $proceed = function () {
            $this->proceedCalled = true;
        };
        $this->plugin->aroundExecute($this->subject, $proceed, $reservations);
    }
}
