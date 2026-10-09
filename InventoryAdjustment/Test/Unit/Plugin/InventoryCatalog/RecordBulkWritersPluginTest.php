<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Test\Unit\Plugin\InventoryCatalog;

use Magento\InventoryAdjustment\Model\Actor;
use Magento\InventoryAdjustment\Model\AdjustmentContext;
use Magento\InventoryAdjustment\Model\AdjustmentOrigin;
use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;
use Magento\InventoryAdjustment\Model\TransferMetadataFactory;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\RecordBulkInventoryTransferPlugin;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\RecordBulkSourceUnassignPlugin;
use Magento\InventoryAdjustment\Plugin\InventoryCatalog\RecordPartialTransferPlugin;
use Magento\InventoryAdjustmentApi\Api\Data\AdjustmentMetadataInterface;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer;
use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceUnassign;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecordBulkWritersPluginTest extends TestCase
{
    /**
     * @var AdjustmentContext
     */
    private AdjustmentContext $context;

    /**
     * @var AdjustmentOrigin
     */
    private AdjustmentOrigin $origin;

    /**
     * @var array
     */
    private array $keys = [];

    /**
     * @var AdjustmentMetadataInterface|null
     */
    private ?AdjustmentMetadataInterface $seen = null;

    /**
     * @var AdjustmentRecorder|MockObject
     */
    private $recorder;

    protected function setUp(): void
    {
        $this->context = new AdjustmentContext();
        $this->origin = new AdjustmentOrigin();
        $this->recorder = $this->createMock(AdjustmentRecorder::class);
        $this->recorder->method('record')->willReturnCallback(function (array $keys, callable $write) {
            $this->keys = $keys;
            $this->seen = $this->context->getCurrent();
            return $write();
        });
    }

    public function testUnassignRecordsEverySkuInEverySourceAsOther(): void
    {
        $count = (new RecordBulkSourceUnassignPlugin($this->recorder, new SourceItemKeys(), $this->context))
            ->aroundExecute($this->createMock(BulkSourceUnassign::class), fn () => 4, ['A', 'B'], ['s1', 's2']);

        self::assertSame(4, $count);
        self::assertCount(4, $this->keys);
        self::assertSame('other', $this->seen->getReason()->value);
    }

    public function testBulkTransferRecordsBothSourcesUnderTheBulkUuid(): void
    {
        $this->origin->run(new Actor(ActorType::Admin, '1'), 'bulk-1', function (): void {
            (new RecordBulkInventoryTransferPlugin(
                $this->recorder,
                new SourceItemKeys(),
                $this->context,
                new TransferMetadataFactory($this->origin)
            ))->aroundExecute(
                $this->createMock(BulkInventoryTransfer::class),
                fn () => null,
                ['A'],
                'from',
                'to',
                true
            );
        });

        self::assertSame(
            [['source_code' => 'from', 'sku' => 'A'], ['source_code' => 'to', 'sku' => 'A']],
            $this->keys
        );
        $this->assertTransfer('bulk-1');
    }

    public function testPartialTransferRecordsBothSourcesOfItsSku(): void
    {
        $item = $this->createMock(PartialInventoryTransferItemInterface::class);
        $item->method('getSku')->willReturn('A');

        (new RecordPartialTransferPlugin(
            $this->recorder,
            new SourceItemKeys(),
            $this->context,
            new TransferMetadataFactory($this->origin)
        ))->aroundExecute($this->createMock(TransferInventoryPartially::class), fn () => null, $item, 'from', 'to');

        self::assertSame(
            [['source_code' => 'from', 'sku' => 'A'], ['source_code' => 'to', 'sku' => 'A']],
            $this->keys
        );
        $this->assertTransfer(null);
    }

    private function assertTransfer(?string $referenceId): void
    {
        self::assertSame('transfer_out', $this->seen->getReason()->value);
        self::assertSame('transfer_in', $this->seen->getInboundReason()->value);
        self::assertSame('transfer', $this->seen->getReferenceType());
        self::assertSame($referenceId, $this->seen->getReferenceId());
    }
}
