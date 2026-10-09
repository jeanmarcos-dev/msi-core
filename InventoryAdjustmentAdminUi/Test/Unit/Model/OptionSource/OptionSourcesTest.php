<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustmentAdminUi\Test\Unit\Model\OptionSource;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryAdjustmentAdminUi\Model\OptionSource\ActorTypeOptions;
use Magento\InventoryAdjustmentAdminUi\Model\OptionSource\ReasonOptions;
use Magento\InventoryAdjustmentAdminUi\Model\OptionSource\SourceOptions;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\Data\SourceSearchResultsInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;
use PHPUnit\Framework\TestCase;

class OptionSourcesTest extends TestCase
{
    public function testEveryReasonOfTheClosedListIsAnOption(): void
    {
        $values = array_column((new ReasonOptions())->toOptionArray(), 'value');

        self::assertContains('transfer_in', $values);
        self::assertContains('legacy_bridge', $values);
        self::assertCount(16, $values);
    }

    public function testEveryActorTypeIsAnOption(): void
    {
        self::assertSame(
            ['admin', 'integration', 'import', 'system', 'customer'],
            array_column((new ActorTypeOptions())->toOptionArray(), 'value')
        );
    }

    public function testEverySourceIsAnOptionEvenWhenDisabled(): void
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getSourceCode')->willReturn('warehouse_1');
        $source->method('getName')->willReturn('Warehouse');
        $results = $this->createMock(SourceSearchResultsInterface::class);
        $results->method('getItems')->willReturn([$source]);
        $repository = $this->createMock(SourceRepositoryInterface::class);
        $repository->method('getList')->willReturn($results);
        $builder = $this->createMock(SearchCriteriaBuilder::class);
        $builder->expects(self::never())->method('addFilter');
        $builder->method('create')->willReturn(new SearchCriteria());

        self::assertSame(
            [['value' => 'warehouse_1', 'label' => 'Warehouse (warehouse_1)']],
            (new SourceOptions($repository, $builder))->toOptionArray()
        );
    }
}
