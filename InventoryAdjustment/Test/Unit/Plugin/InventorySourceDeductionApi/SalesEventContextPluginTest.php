<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\InventorySourceDeductionApi;

use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustment\Plugin\InventorySourceDeductionApi\SalesEventContextPlugin;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionRequestInterface;
use Magento\InventorySourceDeductionApi\Model\SourceDeductionServiceInterface;
use PHPUnit\Framework\TestCase;

class SalesEventContextPluginTest extends TestCase
{
    public function testMapsEverySalesEventToItsReason(): void
    {
        $cases = [
            SalesEventInterface::EVENT_SHIPMENT_CREATED => AdjustmentReason::Shipment,
            SalesEventInterface::EVENT_INVOICE_CREATED => AdjustmentReason::Shipment,
            SalesEventInterface::EVENT_CREDITMEMO_CREATED => AdjustmentReason::ReturnRestock,
            SalesEventInterface::EVENT_ORDER_CANCELED => AdjustmentReason::Other,
        ];
        foreach ($cases as $type => $reason) {
            $metadata = $this->metadataSeenDuring($type, new AdjustmentContext());

            self::assertSame($reason, $metadata->getReason(), $type);
            self::assertSame('order', $metadata->getReferenceType());
            self::assertSame('42', $metadata->getReferenceId());
        }
    }

    public function testKeepsAMoreSpecificContextAlreadyRunning(): void
    {
        $context = new AdjustmentContext();
        $shipment = new AdjustmentMetadata(AdjustmentReason::Shipment, 'shipment', '9');

        $seen = $context->run(
            $shipment,
            fn () => $this->metadataSeenDuring(SalesEventInterface::EVENT_SHIPMENT_CREATED, $context)
        );

        self::assertSame($shipment, $seen);
    }

    private function metadataSeenDuring(string $type, AdjustmentContext $context): ?AdjustmentMetadata
    {
        $event = $this->createMock(SalesEventInterface::class);
        $event->method('getType')->willReturn($type);
        $event->method('getObjectType')->willReturn(SalesEventInterface::OBJECT_TYPE_ORDER);
        $event->method('getObjectId')->willReturn('42');
        $request = $this->createMock(SourceDeductionRequestInterface::class);
        $request->method('getSalesEvent')->willReturn($event);

        $seen = null;
        (new SalesEventContextPlugin($context))->aroundExecute(
            $this->createMock(SourceDeductionServiceInterface::class),
            function () use ($context, &$seen): void {
                $seen = $context->getCurrent();
            },
            $request
        );

        return $seen;
    }
}
