<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\SalesInventory;

use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustment\Plugin\SalesInventory\CreditmemoReturnContextPlugin;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\InventorySales\Plugin\SalesInventory\ProcessReturnQtyOnCreditMemoPlugin;
use Magento\SalesInventory\Model\Order\ReturnProcessor;
use PHPUnit\Framework\TestCase;

class CreditmemoReturnContextPluginTest extends TestCase
{
    public function testRecordsTheReturnUnderTheCreditMemo(): void
    {
        $metadata = $this->metadataSeenDuring('15', new AdjustmentContext());

        self::assertSame(AdjustmentReason::ReturnRestock, $metadata->getReason());
        self::assertSame('creditmemo', $metadata->getReferenceType());
        self::assertSame('15', $metadata->getReferenceId());
    }

    public function testKeepsAContextAlreadyRunning(): void
    {
        $context = new AdjustmentContext();
        $outer = new AdjustmentMetadata(AdjustmentReason::Other, 'order', '3');

        $seen = $context->run($outer, fn () => $this->metadataSeenDuring('15', $context));

        self::assertSame($outer, $seen);
    }

    public function testLeavesTheOrderReferenceWhenTheCreditMemoHasNoId(): void
    {
        self::assertNull($this->metadataSeenDuring(null, new AdjustmentContext()));
    }

    public function testPassesTheArgumentsThrough(): void
    {
        $returnProcessor = $this->createMock(ReturnProcessor::class);
        $returnProcessorProceed = fn () => null;
        $creditmemo = $this->createMock(CreditmemoInterface::class);
        $order = $this->createMock(OrderInterface::class);
        $received = null;

        (new CreditmemoReturnContextPlugin(new AdjustmentContext()))->aroundAroundExecute(
            $this->createMock(ProcessReturnQtyOnCreditMemoPlugin::class),
            function (...$arguments) use (&$received): void {
                $received = $arguments;
            },
            $returnProcessor,
            $returnProcessorProceed,
            $creditmemo,
            $order,
            ['5'],
            true
        );

        self::assertSame([$returnProcessor, $returnProcessorProceed, $creditmemo, $order, ['5'], true], $received);
    }

    private function metadataSeenDuring(?string $creditmemoId, AdjustmentContext $context): ?AdjustmentMetadata
    {
        $creditmemo = $this->createMock(CreditmemoInterface::class);
        $creditmemo->method('getEntityId')->willReturn($creditmemoId);

        $seen = null;
        (new CreditmemoReturnContextPlugin($context))->aroundAroundExecute(
            $this->createMock(ProcessReturnQtyOnCreditMemoPlugin::class),
            function () use ($context, &$seen): void {
                $seen = $context->getCurrent();
            },
            $this->createMock(ReturnProcessor::class),
            fn () => null,
            $creditmemo,
            $this->createMock(OrderInterface::class)
        );

        return $seen;
    }
}
