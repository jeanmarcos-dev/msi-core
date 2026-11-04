<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Test\Unit\Model\Queue\UpdateIndexSalabilityStatus;

use Magento\InventoryCatalogApi\Model\GetParentSkusOfChildrenSkusInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryIndexer\Indexer\Stock\DataProvider;
use Magento\InventoryIndexer\Model\Queue\GetSalabilityDataForUpdate;
use Magento\InventoryIndexer\Model\Queue\ReservationData;
use Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus\IndexProcessor;
use Magento\InventoryIndexer\Model\ResourceModel\UpdateIsSalable;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexName;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexStructureInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use PHPUnit\Framework\TestCase;

class IndexProcessorTest extends TestCase
{
    private const STOCK_ID = 3;
    private const TYPES = [
        'child' => 'simple',
        'other-child' => 'simple',
        'conf' => 'configurable',
        'group' => 'grouped',
        'bundle' => 'bundle',
    ];

    public function testWritesTheChildAndTheConfigurableItLeftWithoutSalableChildren(): void
    {
        [$processor, $writes] = $this->createProcessor(
            ['child' => false, 'conf' => true],
            ['child' => ['conf']],
            ['conf' => '1'],
            ['configurable' => ['conf' => '0']]
        );

        $this->assertSame(['child' => false, 'conf' => false], $processor->execute($this->reservation()));
        $this->assertSame([['child' => false], ['conf' => false]], $writes->items);
    }

    public function testLeavesAParentWhoseChildrenStillMakeItSalable(): void
    {
        [$processor, $writes] = $this->createProcessor(
            ['child' => false],
            ['child' => ['conf', 'group']],
            ['conf' => '1', 'group' => '1'],
            ['configurable' => ['conf' => '1'], 'grouped' => ['group' => '1']]
        );

        $this->assertSame(['child' => false], $processor->execute($this->reservation()));
        $this->assertSame([['child' => false], []], $writes->items);
    }

    public function testBringsBackAGroupedParentWhenAChildBecomesSalable(): void
    {
        [$processor] = $this->createProcessor(
            ['child' => true],
            ['child' => ['group']],
            ['group' => '0'],
            ['grouped' => ['group' => '1']]
        );

        $this->assertSame(['child' => true, 'group' => true], $processor->execute($this->reservation()));
    }

    public function testLooksUpNoParentWhenNoChildChanged(): void
    {
        [$processor, $writes] = $this->createProcessor([], [], [], []);

        $this->assertSame([], $processor->execute($this->reservation()));
        $this->assertSame([[], []], $writes->items);
    }

    public function testLeavesABundleToTheSalabilityCheck(): void
    {
        [$processor, $writes] = $this->createProcessor(
            ['other-child' => false, 'bundle' => false],
            ['other-child' => ['bundle'], 'bundle' => []],
            [],
            []
        );

        $this->assertSame(
            ['other-child' => false, 'bundle' => false],
            $processor->execute($this->reservation())
        );
        $this->assertSame([['other-child' => false, 'bundle' => false], []], $writes->items);
    }

    private function createProcessor(
        array $salabilityChanges,
        array $parentsByChild,
        array $storedParents,
        array $computedByType
    ): array {
        $writes = new \stdClass();
        $writes->items = [];

        $indexNameBuilder = $this->createMock(IndexNameBuilder::class);
        $indexNameBuilder->method('setIndexId')->willReturnSelf();
        $indexNameBuilder->method('addDimension')->willReturnSelf();
        $indexNameBuilder->method('setAlias')->willReturnSelf();
        $indexNameBuilder->method('build')->willReturn($this->createMock(IndexName::class));

        $indexStructure = $this->createMock(IndexStructureInterface::class);
        $indexStructure->method('isExist')->willReturn(true);

        $updateIsSalable = $this->createMock(UpdateIsSalable::class);
        $updateIsSalable->method('execute')->willReturnCallback(
            function (...$arguments) use ($writes): void {
                $writes->items[] = $arguments[1];
            }
        );

        $getSalabilityDataForUpdate = $this->createMock(GetSalabilityDataForUpdate::class);
        $getSalabilityDataForUpdate->method('execute')->willReturn($salabilityChanges);

        $getParentSkus = $this->createMock(GetParentSkusOfChildrenSkusInterface::class);
        if ($parentsByChild) {
            $getParentSkus->method('execute')->willReturnCallback(
                fn (array $skus): array => array_intersect_key($parentsByChild, array_flip($skus))
            );
        } else {
            $getParentSkus->expects($this->never())->method('execute');
        }

        $getTypes = $this->createMock(GetProductTypesBySkusInterface::class);
        $getTypes->method('execute')->willReturnCallback(
            fn (array $skus): array => array_intersect_key(self::TYPES, array_flip($skus))
        );

        $getStockItemsData = $this->createMock(GetStockItemsDataInterface::class);
        $getStockItemsData->method('execute')->willReturnCallback(
            function (array $skus, int $stockId) use ($storedParents): array {
                $this->assertSame(self::STOCK_ID, $stockId);
                $rows = [];
                foreach (array_intersect_key($storedParents, array_flip($skus)) as $sku => $isSalable) {
                    $rows[$sku] = [GetStockItemsDataInterface::IS_SALABLE => $isSalable];
                }
                return $rows;
            }
        );

        $dataProvider = $this->createMock(DataProvider::class);
        if ($computedByType) {
            $dataProvider->method('getSiblingsData')->willReturnCallback(
                function (int $stockId, string $type, array $skus) use ($computedByType): array {
                    $this->assertSame(self::STOCK_ID, $stockId);
                    $rows = [];
                    foreach (array_intersect_key($computedByType[$type], array_flip($skus)) as $sku => $isSalable) {
                        $rows[$sku] = ['sku' => $sku, 'is_salable' => $isSalable];
                    }
                    return $rows;
                }
            );
        } else {
            $dataProvider->expects($this->never())->method('getSiblingsData');
        }

        $processor = new IndexProcessor(
            $indexNameBuilder,
            $indexStructure,
            $updateIsSalable,
            $getSalabilityDataForUpdate,
            $getParentSkus,
            $getTypes,
            $getStockItemsData,
            $dataProvider,
            ['configurable', 'grouped']
        );

        return [$processor, $writes];
    }

    private function reservation(): ReservationData
    {
        return new ReservationData(['child'], self::STOCK_ID);
    }
}
