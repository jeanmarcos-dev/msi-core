<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemDataBySkusAndSources;
use Magento\InventorySales\Model\SourceReservation\GetSourceAvailabilityBySkus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GetSourceAvailabilityBySkusTest extends TestCase
{
    /**
     * @var GetSourceItemDataBySkusAndSources|MockObject
     */
    private $getSourceItemData;

    /**
     * @var GetReservationsQuantityBySkusAndSources|MockObject
     */
    private $getReservationsQuantity;

    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $sourceReservationsConfig;

    /**
     * @var GetSourceAvailabilityBySkus
     */
    private $getSourceAvailability;

    protected function setUp(): void
    {
        $this->getSourceItemData = $this->createMock(GetSourceItemDataBySkusAndSources::class);
        $this->getReservationsQuantity = $this->createMock(GetReservationsQuantityBySkusAndSources::class);
        $this->sourceReservationsConfig = $this->createMock(SourceReservationsConfig::class);

        $this->getSourceAvailability = new GetSourceAvailabilityBySkus(
            $this->getSourceItemData,
            $this->getReservationsQuantity,
            $this->sourceReservationsConfig
        );
    }

    public function testNetsThePhysicalQuantityAgainstTheSourceReservationBalance(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getSourceItemData->method('execute')->willReturn(
            ['src_a' => ['sku-1' => $this->sourceItem(5.0, SourceItemInterface::STATUS_IN_STOCK)]]
        );
        $this->getReservationsQuantity->method('execute')->willReturn(['src_a' => ['sku-1' => -2.0]]);

        $result = $this->getSourceAvailability->execute(['sku-1'], ['src_a']);

        self::assertSame(5.0, $result['src_a']['sku-1']['physical']);
        self::assertSame(-2.0, $result['src_a']['sku-1']['reserved']);
        self::assertSame(3.0, $result['src_a']['sku-1']['salable']);
        self::assertFalse($result['src_a']['sku-1']['source_item_out_of_stock']);
    }

    public function testDegradesToThePhysicalQuantityWhenSourceReservationsAreDisabled(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(false);
        $this->getSourceItemData->method('execute')->willReturn(
            ['src_a' => ['sku-1' => $this->sourceItem(7.0, SourceItemInterface::STATUS_IN_STOCK)]]
        );
        $this->getReservationsQuantity->expects(self::never())->method('execute');

        $result = $this->getSourceAvailability->execute(['sku-1'], ['src_a']);

        self::assertSame(7.0, $result['src_a']['sku-1']['physical']);
        self::assertSame(0.0, $result['src_a']['sku-1']['reserved']);
        self::assertSame(7.0, $result['src_a']['sku-1']['salable']);
    }

    public function testReportsZeroSalableButKeepsThePhysicalQuantityWhenTheSourceItemIsOutOfStock(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getSourceItemData->method('execute')->willReturn(
            ['src_a' => ['sku-1' => $this->sourceItem(10.0, SourceItemInterface::STATUS_OUT_OF_STOCK)]]
        );
        $this->getReservationsQuantity->method('execute')->willReturn([]);

        $result = $this->getSourceAvailability->execute(['sku-1'], ['src_a']);

        self::assertSame(10.0, $result['src_a']['sku-1']['physical']);
        self::assertSame(0.0, $result['src_a']['sku-1']['salable']);
        self::assertTrue($result['src_a']['sku-1']['source_item_out_of_stock']);
    }

    public function testClampsTheSalableQuantityToZeroWhenReservationsExceedThePhysicalQuantity(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getSourceItemData->method('execute')->willReturn(
            ['src_a' => ['sku-1' => $this->sourceItem(3.0, SourceItemInterface::STATUS_IN_STOCK)]]
        );
        $this->getReservationsQuantity->method('execute')->willReturn(['src_a' => ['sku-1' => -8.0]]);

        $result = $this->getSourceAvailability->execute(['sku-1'], ['src_a']);

        self::assertSame(-8.0, $result['src_a']['sku-1']['reserved']);
        self::assertSame(0.0, $result['src_a']['sku-1']['salable']);
    }

    public function testOmitsSourcesWithoutASourceItemForTheSku(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getSourceItemData->method('execute')->willReturn(
            ['src_a' => ['sku-1' => $this->sourceItem(1.0, SourceItemInterface::STATUS_IN_STOCK)]]
        );
        $this->getReservationsQuantity->method('execute')->willReturn(['src_b' => ['sku-1' => -4.0]]);

        $result = $this->getSourceAvailability->execute(['sku-1'], ['src_a', 'src_b']);

        self::assertArrayNotHasKey('src_b', $result);
    }

    public function testQueriesNothingWhenTheSkuListIsEmpty(): void
    {
        $this->getSourceItemData->expects(self::never())->method('execute');
        $this->getReservationsQuantity->expects(self::never())->method('execute');

        self::assertSame([], $this->getSourceAvailability->execute([], ['src_a']));
    }

    public function testQueriesNothingWhenTheSourceListIsEmpty(): void
    {
        $this->getSourceItemData->expects(self::never())->method('execute');
        $this->getReservationsQuantity->expects(self::never())->method('execute');

        self::assertSame([], $this->getSourceAvailability->execute(['sku-1'], []));
    }

    /**
     * Build a source item row as returned by the resource model.
     *
     * @param float $quantity
     * @param int $status
     * @return array<string, float|int>
     */
    private function sourceItem(float $quantity, int $status): array
    {
        return ['quantity' => $quantity, 'status' => $status];
    }
}
