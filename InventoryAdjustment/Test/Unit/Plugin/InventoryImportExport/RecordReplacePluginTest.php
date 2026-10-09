<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\InventoryImportExport;

use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;
use Magento\InventoryAdjustment\Plugin\InventoryImportExport\RecordReplacePlugin;
use Magento\InventoryImportExport\Model\Import\Command\Replace;
use PHPUnit\Framework\TestCase;

class RecordReplacePluginTest extends TestCase
{
    public function testTheDeleteAndTheSaveOfABunchAreOneNetRecording(): void
    {
        $bunch = [['source_code' => 'a', 'sku' => 'X', 'quantity' => '4', 'status' => '1']];
        $recorder = $this->createMock(AdjustmentRecorder::class);
        $recorder->expects(self::once())
            ->method('recordNet')
            ->with([['source_code' => 'a', 'sku' => 'X']])
            ->willReturnCallback(fn (array $keys, callable $write) => $write());
        $calls = 0;

        (new RecordReplacePlugin($recorder, new SourceItemKeys()))->aroundExecute(
            $this->createMock(Replace::class),
            function (array $given) use ($bunch, &$calls): void {
                self::assertSame($bunch, $given);
                $calls++;
            },
            $bunch
        );

        self::assertSame(1, $calls);
    }
}
