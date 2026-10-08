<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Model;

use Magento\InventoryAdjustment\Model\Actor;
use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustment\Model\AdjustmentRowsBuilder;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use PHPUnit\Framework\TestCase;

class AdjustmentRowsBuilderTest extends TestCase
{
    public function testUnchangedItemsWriteNothing(): void
    {
        self::assertSame([], $this->build($this->item(10.0, 1), $this->item(10.0, 1)));
    }

    public function testQuantityChangeWritesTheDeltaAndTheResult(): void
    {
        $rows = $this->build($this->item(10.0, 1), $this->item(7.0, 1));

        self::assertCount(1, $rows);
        self::assertSame(-3.0, $rows[0]['delta']);
        self::assertSame(7.0, $rows[0]['quantity_after']);
        self::assertNull($rows[0]['status_before']);
        self::assertNull($rows[0]['status_after']);
    }

    public function testStatusOnlyChangeWritesAZeroDeltaWithBothStatuses(): void
    {
        $rows = $this->build($this->item(5.0, 1), $this->item(5.0, 0));

        self::assertSame(0.0, $rows[0]['delta']);
        self::assertSame(1, $rows[0]['status_before']);
        self::assertSame(0, $rows[0]['status_after']);
    }

    public function testNewItemWithStockWritesItsQuantity(): void
    {
        $rows = $this->build([], $this->item(4.0, 1));

        self::assertSame(4.0, $rows[0]['delta']);
        self::assertNull($rows[0]['status_before']);
        self::assertSame(1, $rows[0]['status_after']);
    }

    public function testNewItemWithoutStockWritesNothing(): void
    {
        self::assertSame([], $this->build([], $this->item(0.0, 1)));
    }

    public function testDeletedItemWritesItsWholeQuantityOut(): void
    {
        $rows = $this->build($this->item(6.0, 1), []);

        self::assertSame(-6.0, $rows[0]['delta']);
        self::assertSame(0.0, $rows[0]['quantity_after']);
        self::assertSame(1, $rows[0]['status_before']);
        self::assertNull($rows[0]['status_after']);
    }

    public function testDeletedItemWithoutStockWritesNothing(): void
    {
        self::assertSame([], $this->build($this->item(0.0, 0), []));
    }

    public function testFloatingPointNoiseIsNotAChange(): void
    {
        self::assertSame([], $this->build($this->item(0.3, 1), $this->item(0.1 + 0.2, 1)));

        $rows = $this->build($this->item(0.1, 1), $this->item(0.3, 1));
        self::assertSame(0.2, $rows[0]['delta']);
    }

    public function testNumericSkusStayStrings(): void
    {
        $rows = (new AdjustmentRowsBuilder())->build(
            [],
            ['src' => ['123' => ['quantity' => 1.0, 'status' => 1]]],
            new AdjustmentMetadata(AdjustmentReason::Other),
            new Actor(ActorType::System)
        );

        self::assertSame('123', $rows[0]['sku']);
    }

    public function testCopiesTheMetadataAndTheActor(): void
    {
        $rows = (new AdjustmentRowsBuilder())->build(
            $this->item(1.0, 1),
            $this->item(2.0, 1),
            new AdjustmentMetadata(AdjustmentReason::Shipment, 'shipment', '12', 'req-1', 'note'),
            new Actor(ActorType::Admin, '3', 'jane')
        );

        self::assertSame(
            [
                'source_code' => 'src',
                'sku' => 'SKU-1',
                'state' => 'available',
                'delta' => 1.0,
                'quantity_after' => 2.0,
                'status_before' => null,
                'status_after' => null,
                'reason' => 'shipment',
                'actor_type' => 'admin',
                'actor_id' => '3',
                'actor_label' => 'jane',
                'reference_type' => 'shipment',
                'reference_id' => '12',
                'request_id' => 'req-1',
                'note' => 'note',
            ],
            $rows[0]
        );
    }

    private function build(array $before, array $after): array
    {
        return (new AdjustmentRowsBuilder())->build(
            $before,
            $after,
            new AdjustmentMetadata(AdjustmentReason::Other),
            new Actor(ActorType::System)
        );
    }

    private function item(float $quantity, int $status): array
    {
        return ['src' => ['SKU-1' => ['quantity' => $quantity, 'status' => $status]]];
    }
}
