<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\InventoryShipping;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Plugin\InventoryShipping\ShipmentContextPlugin;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventoryShipping\Observer\SourceDeductionProcessor;
use Magento\Sales\Model\Order\Shipment;
use PHPUnit\Framework\TestCase;

class ShipmentContextPluginTest extends TestCase
{
    public function testNewShipmentDeductsUnderItsOwnReference(): void
    {
        $seen = $this->runWith(new AdjustmentContext(), $this->shipment(null, 15));

        self::assertSame(AdjustmentReason::Shipment, $seen->getReason());
        self::assertSame('shipment', $seen->getReferenceType());
        self::assertSame('15', $seen->getReferenceId());
    }

    public function testAlreadySavedShipmentAddsNoContext(): void
    {
        self::assertNull($this->runWith(new AdjustmentContext(), $this->shipment(15, 15)));
    }

    private function runWith(AdjustmentContext $context, Shipment $shipment): mixed
    {
        $observer = new Observer(['event' => new Event(['shipment' => $shipment])]);
        $seen = null;
        (new ShipmentContextPlugin($context))->aroundExecute(
            $this->createMock(SourceDeductionProcessor::class),
            function () use ($context, &$seen): void {
                $seen = $context->getCurrent();
            },
            $observer
        );

        return $seen;
    }

    private function shipment(?int $origId, int $id): Shipment
    {
        $shipment = $this->createMock(Shipment::class);
        $shipment->method('getOrigData')->with('entity_id')->willReturn($origId);
        $shipment->method('getEntityId')->willReturn($id);

        return $shipment;
    }
}
