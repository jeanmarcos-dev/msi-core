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
use Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus\DerivedParentSalability;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use PHPUnit\Framework\TestCase;

class DerivedParentSalabilityTest extends TestCase
{
    private const STOCK_ID = 3;
    private const TYPES = [
        'child' => 'simple',
        'other-child' => 'simple',
        'conf' => 'configurable',
        'group' => 'grouped',
        'bundle' => 'bundle',
    ];

    public function testDropsTheDerivedParentsAndKeepsTheRest(): void
    {
        $salability = $this->createSalability([], [], []);

        $this->assertSame(
            ['child' => false, 'bundle' => false],
            $salability->withoutDerivedParents(['child' => false, 'conf' => true, 'group' => true, 'bundle' => false])
        );
    }

    public function testReportsAConfigurableLeftWithoutSalableChildren(): void
    {
        $salability = $this->createSalability(
            ['child' => ['conf']],
            ['conf' => '1'],
            ['configurable' => ['conf' => '0']]
        );

        $this->assertSame(['conf' => false], $salability->getChanges(['child'], self::STOCK_ID));
    }

    public function testReportsAGroupedParentBroughtBackByAChild(): void
    {
        $salability = $this->createSalability(
            ['child' => ['group']],
            ['group' => '0'],
            ['grouped' => ['group' => '1']]
        );

        $this->assertSame(['group' => true], $salability->getChanges(['child'], self::STOCK_ID));
    }

    public function testLeavesOutParentsWhoseSalabilityDidNotChange(): void
    {
        $salability = $this->createSalability(
            ['child' => ['conf', 'group']],
            ['conf' => '1', 'group' => '1'],
            ['configurable' => ['conf' => '1'], 'grouped' => ['group' => '1']]
        );

        $this->assertSame([], $salability->getChanges(['child'], self::STOCK_ID));
    }

    public function testLooksUpNoParentWithoutChildren(): void
    {
        $salability = $this->createSalability([], [], []);

        $this->assertSame([], $salability->getChanges([], self::STOCK_ID));
    }

    public function testLeavesABundleToTheSalabilityCheck(): void
    {
        $salability = $this->createSalability(['other-child' => ['bundle']], [], []);

        $this->assertSame([], $salability->getChanges(['other-child'], self::STOCK_ID));
    }

    private function createSalability(
        array $parentsByChild,
        array $storedParents,
        array $computedByType
    ): DerivedParentSalability {
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

        return new DerivedParentSalability(
            $getParentSkus,
            $getTypes,
            $getStockItemsData,
            $dataProvider,
            ['configurable', 'grouped']
        );
    }
}
