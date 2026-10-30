<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Plugin\InventorySales;

use Magento\InventoryIndexer\Model\Queue\EnqueueSalabilityUpdate;
use Magento\InventoryIndexer\Plugin\InventorySales\EnqueueAfterReconcileOrderReservations;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnqueueAfterReconcileOrderReservationsTest extends TestCase
{
    /**
     * @var EnqueueSalabilityUpdate|MockObject
     */
    private $enqueueSalabilityUpdate;

    /**
     * @var EnqueueAfterReconcileOrderReservations
     */
    private $plugin;

    /**
     * @var ReconcileOrderReservations|MockObject
     */
    private $subject;

    protected function setUp(): void
    {
        $this->enqueueSalabilityUpdate = $this->createMock(EnqueueSalabilityUpdate::class);
        $this->subject = $this->createStub(ReconcileOrderReservations::class);
        $this->plugin = new EnqueueAfterReconcileOrderReservations($this->enqueueSalabilityUpdate);
    }

    public function testEnqueuesOncePerStockWithTheSkusItWasCompensatedFor(): void
    {
        $compensations = [
            $this->compensation(10, 'SKU-1', 'a'),
            $this->compensation(10, 'SKU-1', 'b'),
            $this->compensation(10, 'SKU-2', 'a'),
            $this->compensation(20, 'SKU-1', 'c'),
        ];
        $calls = [];
        $this->enqueueSalabilityUpdate->expects(self::exactly(2))
            ->method('execute')
            ->willReturnCallback(static function (int $stockId, array $skus) use (&$calls): void {
                $calls[$stockId] = $skus;
            });

        $result = $this->plugin->afterExecute($this->subject, $compensations, 5, '000000001', 'canceled');

        self::assertSame($compensations, $result);
        self::assertSame([10 => ['SKU-1', 'SKU-2'], 20 => ['SKU-1']], $calls);
    }

    public function testEnqueuesAfterReleasingAnAbandonedPlacement(): void
    {
        $compensations = [$this->compensation(10, 'SKU-1', 'a')];
        $this->enqueueSalabilityUpdate->expects(self::once())->method('execute')->with(10, ['SKU-1']);

        $result = $this->plugin->afterExecuteAbandoned($this->subject, $compensations, '000000001', 99);

        self::assertSame($compensations, $result);
    }

    public function testDoesNothingOnADryRun(): void
    {
        $this->enqueueSalabilityUpdate->expects(self::never())->method('execute');

        $this->plugin->afterExecute(
            $this->subject,
            [$this->compensation(10, 'SKU-1', 'a')],
            5,
            '000000001',
            'canceled',
            true
        );
        $this->plugin->afterExecuteAbandoned(
            $this->subject,
            [$this->compensation(10, 'SKU-1', 'a')],
            '000000001',
            99,
            true
        );
    }

    public function testDoesNothingWhenNothingWasCompensated(): void
    {
        $this->enqueueSalabilityUpdate->expects(self::never())->method('execute');

        self::assertSame([], $this->plugin->afterExecute($this->subject, [], 5, '000000001', 'canceled'));
    }

    /**
     * @param int $stockId
     * @param string $sku
     * @param string $sourceCode
     * @return array
     */
    private function compensation(int $stockId, string $sku, string $sourceCode): array
    {
        return ['stock_id' => $stockId, 'sku' => $sku, 'source_code' => $sourceCode, 'quantity' => 1.0];
    }
}
