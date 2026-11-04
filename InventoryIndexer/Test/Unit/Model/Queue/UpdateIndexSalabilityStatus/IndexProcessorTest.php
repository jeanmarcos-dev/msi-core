<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model\Queue\UpdateIndexSalabilityStatus;

use Magento\InventoryIndexer\Model\Queue\GetSalabilityDataForUpdate;
use Magento\InventoryIndexer\Model\Queue\ReservationData;
use Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus\DerivedParentSalability;
use Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus\IndexProcessor;
use Magento\InventoryIndexer\Model\ResourceModel\UpdateIsSalable;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;
use PHPUnit\Framework\TestCase;

class IndexProcessorTest extends TestCase
{
    public function testWritesTheChildrenThenTheirDerivedParentsAndReturnsBoth(): void
    {
        $reservation = new ReservationData(['child'], 3);

        $indexNameBuilder = $this->createMock(IndexNameBuilder::class);
        $indexNameBuilder->method('setIndexId')->willReturnSelf();
        $indexNameBuilder->method('addDimension')->willReturnSelf();
        $indexNameBuilder->method('setAlias')->willReturnSelf();
        $indexNameBuilder->method('build')->willReturn($this->createMock(IndexName::class));

        $indexStructure = $this->createMock(IndexStructureInterface::class);
        $indexStructure->method('isExist')->willReturn(true);

        $getSalabilityDataForUpdate = $this->createMock(GetSalabilityDataForUpdate::class);
        $getSalabilityDataForUpdate->method('execute')->with($reservation)
            ->willReturn(['child' => false, 'conf' => true]);

        $derivedParentSalability = $this->createMock(DerivedParentSalability::class);
        $derivedParentSalability->method('withoutDerivedParents')->with(['child' => false, 'conf' => true])
            ->willReturn(['child' => false]);
        $derivedParentSalability->method('getChanges')->with(['child'], 3)->willReturn(['conf' => false]);

        $writes = new \stdClass();
        $writes->items = [];
        $updateIsSalable = $this->createMock(UpdateIsSalable::class);
        $updateIsSalable->method('execute')->willReturnCallback(
            function (...$arguments) use ($writes): void {
                $writes->items[] = $arguments[1];
            }
        );

        $processor = new IndexProcessor(
            $indexNameBuilder,
            $indexStructure,
            $updateIsSalable,
            $getSalabilityDataForUpdate,
            $derivedParentSalability
        );

        $this->assertSame(['child' => false, 'conf' => false], $processor->execute($reservation));
        $this->assertSame([['child' => false], ['conf' => false]], $writes->items);
    }
}
