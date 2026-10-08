<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\Inventory;

use Magento\Inventory\Model\ResourceModel\SourceItem\DecrementQtyForMultipleSourceItem;
use Magento\Inventory\Model\ResourceModel\SourceItem\DeleteMultiple;
use Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple;
use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;
use Magento\InventoryAdjustment\Plugin\Inventory\RecordDecrementPlugin;
use Magento\InventoryAdjustment\Plugin\Inventory\RecordDeleteMultiplePlugin;
use Magento\InventoryAdjustment\Plugin\Inventory\RecordSaveMultiplePlugin;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use PHPUnit\Framework\TestCase;

class RecordSourceItemWritesPluginTest extends TestCase
{
    /**
     * @var array
     */
    private array $recordedKeys = [];

    public function testSaveRecordsEveryItemItWrites(): void
    {
        $items = [$this->item('src_a', 'SKU-1'), $this->item('src_b', 'SKU-2')];
        $plugin = new RecordSaveMultiplePlugin($this->recorder(), new SourceItemKeys());

        $plugin->aroundExecute($this->createMock(SaveMultiple::class), fn (array $passed) => $passed, $items);

        self::assertSame(
            [['source_code' => 'src_a', 'sku' => 'SKU-1'], ['source_code' => 'src_b', 'sku' => 'SKU-2']],
            $this->recordedKeys
        );
    }

    public function testDeleteRecordsEveryItemItRemoves(): void
    {
        $plugin = new RecordDeleteMultiplePlugin($this->recorder(), new SourceItemKeys());

        $plugin->aroundExecute(
            $this->createMock(DeleteMultiple::class),
            fn (array $passed) => $passed,
            [$this->item('src_a', 'SKU-1')]
        );

        self::assertSame([['source_code' => 'src_a', 'sku' => 'SKU-1']], $this->recordedKeys);
    }

    public function testDecrementRecordsTheItemsOfEveryDecrement(): void
    {
        $plugin = new RecordDecrementPlugin($this->recorder(), new SourceItemKeys());

        $plugin->aroundExecute(
            $this->createMock(DecrementQtyForMultipleSourceItem::class),
            fn (array $passed) => $passed,
            [['source_item' => $this->item('src_a', 'SKU-1'), 'qty_to_decrement' => 2.0]]
        );

        self::assertSame([['source_code' => 'src_a', 'sku' => 'SKU-1']], $this->recordedKeys);
    }

    private function recorder(): AdjustmentRecorder
    {
        $recorder = $this->createMock(AdjustmentRecorder::class);
        $recorder->expects(self::once())->method('record')->willReturnCallback(
            function (array $keys, callable $write) {
                $this->recordedKeys = $keys;
                return $write();
            }
        );

        return $recorder;
    }

    private function item(string $sourceCode, string $sku): SourceItemInterface
    {
        $item = $this->createMock(SourceItemInterface::class);
        $item->method('getSourceCode')->willReturn($sourceCode);
        $item->method('getSku')->willReturn($sku);

        return $item;
    }
}
