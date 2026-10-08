<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AdjustmentContextTest extends TestCase
{
    public function testExposesTheMetadataOnlyWhileTheOperationRuns(): void
    {
        $context = new AdjustmentContext();
        $metadata = new AdjustmentMetadata(AdjustmentReason::Count);

        $seen = $context->run($metadata, fn () => $context->getCurrent());

        self::assertSame($metadata, $seen);
        self::assertNull($context->getCurrent());
    }

    public function testTheInnermostMetadataWinsAndTheOuterOneComesBack(): void
    {
        $context = new AdjustmentContext();
        $outer = new AdjustmentMetadata(AdjustmentReason::Import);
        $inner = new AdjustmentMetadata(AdjustmentReason::Shipment);

        $seen = $context->run($outer, function () use ($context, $inner) {
            $innerSeen = $context->run($inner, fn () => $context->getCurrent());
            return [$innerSeen, $context->getCurrent()];
        });

        self::assertSame([$inner, $outer], $seen);
    }

    public function testDropsTheMetadataWhenTheOperationThrows(): void
    {
        $context = new AdjustmentContext();

        try {
            $context->run(
                new AdjustmentMetadata(AdjustmentReason::Other),
                fn () => throw new RuntimeException('write failed')
            );
        } catch (RuntimeException) {
        }

        self::assertNull($context->getCurrent());
    }

    public function testResetClearsAnyLeftoverMetadata(): void
    {
        $context = new AdjustmentContext();
        $context->run(new AdjustmentMetadata(AdjustmentReason::Other), function () use ($context): void {
            $context->_resetState();
        });

        self::assertNull($context->getCurrent());
    }
}
