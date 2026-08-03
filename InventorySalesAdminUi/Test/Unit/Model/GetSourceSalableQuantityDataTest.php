<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Test\Unit\Model;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\SourceReservation\GetSourceAvailabilityBySkus;
use Magento\InventorySalesAdminUi\Model\GetSourceSalableQuantityData;
use Magento\InventorySalesAdminUi\Model\ResourceModel\GetAssignedStockIdsBySku;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetSourceSalableQuantityDataTest extends TestCase
{
    /**
     * @var GetAssignedStockIdsBySku|MockObject
     */
    private $getAssignedStockIdsBySku;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSourcesAssignedToStock;

    /**
     * @var GetSourceAvailabilityBySkus|MockObject
     */
    private $getSourceAvailability;

    /**
     * @var GetSourceSalableQuantityData
     */
    private $getSourceSalableQuantityData;

    protected function setUp(): void
    {
        $this->getAssignedStockIdsBySku = $this->createMock(GetAssignedStockIdsBySku::class);
        $this->getSourcesAssignedToStock = $this->createMock(
            GetSourcesAssignedToStockOrderedByPriorityInterface::class
        );
        $this->getSourceAvailability = $this->createMock(GetSourceAvailabilityBySkus::class);

        $this->getSourceSalableQuantityData = new GetSourceSalableQuantityData(
            $this->getAssignedStockIdsBySku,
            $this->getSourcesAssignedToStock,
            $this->getSourceAvailability
        );
    }

    public function testGroupsTheSourceRowsUnderTheStockTheyAreAssignedTo(): void
    {
        $this->getAssignedStockIdsBySku->method('execute')->willReturn([2, 3]);
        $this->getSourcesAssignedToStock->method('execute')->willReturnMap([
            [2, [$this->source('src_a', 'Source A')]],
            [3, [$this->source('src_b', 'Source B')]],
        ]);
        $this->getSourceAvailability->method('execute')->willReturn([
            'src_a' => ['sku-1' => $this->availability(5.0, -2.0, 3.0)],
            'src_b' => ['sku-1' => $this->availability(10.0, 0.0, 10.0)],
        ]);

        $result = $this->getSourceSalableQuantityData->execute(['sku-1']);

        self::assertSame(['src_a'], array_column($result['sku-1'][2], 'source_code'));
        self::assertSame(['src_b'], array_column($result['sku-1'][3], 'source_code'));
        self::assertSame(3.0, $result['sku-1'][2][0]['salable']);
        self::assertSame(-2.0, $result['sku-1'][2][0]['reserved']);
        self::assertSame('Source A', $result['sku-1'][2][0]['source_name']);
    }

    public function testKeepsThePriorityOrderOfTheSourcesAssignedToTheStock(): void
    {
        $this->getAssignedStockIdsBySku->method('execute')->willReturn([2]);
        $this->getSourcesAssignedToStock->method('execute')->willReturn([
            $this->source('src_b', 'Source B'),
            $this->source('src_a', 'Source A'),
        ]);
        $this->getSourceAvailability->method('execute')->willReturn([
            'src_a' => ['sku-1' => $this->availability(1.0, 0.0, 1.0)],
            'src_b' => ['sku-1' => $this->availability(2.0, 0.0, 2.0)],
        ]);

        $result = $this->getSourceSalableQuantityData->execute(['sku-1']);

        self::assertSame(['src_b', 'src_a'], array_column($result['sku-1'][2], 'source_code'));
    }

    public function testReportsADisabledSourceAsNotSalableWhileKeepingItsQuantities(): void
    {
        $this->getAssignedStockIdsBySku->method('execute')->willReturn([2]);
        $this->getSourcesAssignedToStock->method('execute')->willReturn([
            $this->source('src_a', 'Source A', false),
        ]);
        $this->getSourceAvailability->method('execute')->willReturn([
            'src_a' => ['sku-1' => $this->availability(9.0, -1.0, 8.0)],
        ]);

        $row = $this->getSourceSalableQuantityData->execute(['sku-1'])['sku-1'][2][0];

        self::assertFalse($row['source_enabled']);
        self::assertSame(9.0, $row['physical']);
        self::assertSame(-1.0, $row['reserved']);
        self::assertSame(0.0, $row['salable']);
    }

    public function testOmitsASourceOfTheStockThatHasNoSourceItemForTheSku(): void
    {
        $this->getAssignedStockIdsBySku->method('execute')->willReturn([2]);
        $this->getSourcesAssignedToStock->method('execute')->willReturn([
            $this->source('src_a', 'Source A'),
            $this->source('src_b', 'Source B'),
        ]);
        $this->getSourceAvailability->method('execute')->willReturn([
            'src_a' => ['sku-1' => $this->availability(4.0, 0.0, 4.0)],
        ]);

        $result = $this->getSourceSalableQuantityData->execute(['sku-1']);

        self::assertSame(['src_a'], array_column($result['sku-1'][2], 'source_code'));
    }

    public function testResolvesTheAvailabilityOfEverySkuAndSourceInASingleCall(): void
    {
        $this->getAssignedStockIdsBySku->method('execute')->willReturn([2]);
        $this->getSourcesAssignedToStock->method('execute')->willReturn([
            $this->source('src_a', 'Source A'),
            $this->source('src_b', 'Source B'),
        ]);
        $this->getSourceAvailability->expects(self::once())
            ->method('execute')
            ->with(['sku-1', 'sku-2'], ['src_a', 'src_b'])
            ->willReturn([]);

        $this->getSourceSalableQuantityData->execute(['sku-1', 'sku-2']);
    }

    public function testResolvesTheSourcesOfAStockOnlyOnceAcrossSkus(): void
    {
        $this->getAssignedStockIdsBySku->method('execute')->willReturn([2]);
        $this->getSourcesAssignedToStock->expects(self::once())
            ->method('execute')
            ->with(2)
            ->willReturn([$this->source('src_a', 'Source A')]);
        $this->getSourceAvailability->method('execute')->willReturn([]);

        $this->getSourceSalableQuantityData->execute(['sku-1', 'sku-2']);
    }

    public function testResolvesNothingWhenTheSkuListIsEmpty(): void
    {
        $this->getAssignedStockIdsBySku->expects(self::never())->method('execute');
        $this->getSourceAvailability->expects(self::never())->method('execute');

        self::assertSame([], $this->getSourceSalableQuantityData->execute([]));
    }

    /**
     * Build a source of the stock.
     *
     * @param string $sourceCode
     * @param string $name
     * @param bool $enabled
     * @return SourceInterface|MockObject
     */
    private function source(string $sourceCode, string $name, bool $enabled = true)
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getSourceCode')->willReturn($sourceCode);
        $source->method('getName')->willReturn($name);
        $source->method('isEnabled')->willReturn($enabled);

        return $source;
    }

    /**
     * Build an availability row as returned by the availability service.
     *
     * @param float $physical
     * @param float $reserved
     * @param float $salable
     * @return array<string, bool|float>
     */
    private function availability(float $physical, float $reserved, float $salable): array
    {
        return [
            'physical' => $physical,
            'reserved' => $reserved,
            'salable' => $salable,
            'source_item_out_of_stock' => false,
        ];
    }
}
