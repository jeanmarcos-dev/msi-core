<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Test\Unit\Model;

use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySalesAdminUi\Model\AddSourceSalableQuantityBreakdown;
use Magento\InventorySalesAdminUi\Model\GetSourceSalableQuantityData;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddSourceSalableQuantityBreakdownTest extends TestCase
{
    /**
     * @var GetSourceSalableQuantityData|MockObject
     */
    private $getSourceSalableQuantityData;

    /**
     * @var IsSingleSourceModeInterface|MockObject
     */
    private $isSingleSourceMode;

    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $sourceReservationsConfig;

    /**
     * @var AddSourceSalableQuantityBreakdown
     */
    private $addSourceBreakdown;

    protected function setUp(): void
    {
        $this->getSourceSalableQuantityData = $this->createMock(GetSourceSalableQuantityData::class);
        $this->isSingleSourceMode = $this->createMock(IsSingleSourceModeInterface::class);
        $this->sourceReservationsConfig = $this->createMock(SourceReservationsConfig::class);

        $this->addSourceBreakdown = new AddSourceSalableQuantityBreakdown(
            $this->getSourceSalableQuantityData,
            $this->isSingleSourceMode,
            $this->sourceReservationsConfig
        );
    }

    public function testAddsTheBreakdownToTheStockEntryItBelongsTo(): void
    {
        $this->getSourceSalableQuantityData->method('execute')->willReturn([
            'sku-1' => [
                2 => [$this->sourceRow('src_a')],
                3 => [$this->sourceRow('src_b')],
            ],
        ]);

        $entries = $this->addSourceBreakdown->execute([
            'sku-1' => [$this->stockEntry(2), $this->stockEntry(3)],
        ])['sku-1'];

        self::assertSame('src_a', $entries[0]['sources'][0]['source_code']);
        self::assertSame('src_b', $entries[1]['sources'][0]['source_code']);
    }

    public function testReportsAnEmptyBreakdownForAStockWithoutSourceRows(): void
    {
        $this->getSourceSalableQuantityData->method('execute')->willReturn([]);

        $entries = $this->addSourceBreakdown->execute(['sku-1' => [$this->stockEntry(2)]])['sku-1'];

        self::assertSame([], $entries[0]['sources']);
    }

    public function testReturnsTheEntriesUntouchedInSingleSourceMode(): void
    {
        $this->isSingleSourceMode->method('execute')->willReturn(true);
        $this->getSourceSalableQuantityData->expects(self::never())->method('execute');
        $entriesBySku = ['sku-1' => [$this->stockEntry(1)]];

        self::assertSame($entriesBySku, $this->addSourceBreakdown->execute($entriesBySku));
    }

    public function testSkipsAStockEntryThatDoesNotManageStock(): void
    {
        $this->getSourceSalableQuantityData->method('execute')->willReturn([
            'sku-1' => [2 => [$this->sourceRow('src_a')]],
        ]);
        $entry = $this->stockEntry(2);
        $entry['manage_stock'] = false;

        $entries = $this->addSourceBreakdown->execute(['sku-1' => [$entry]])['sku-1'];

        self::assertArrayNotHasKey('sources', $entries[0]);
    }

    public function testSkipsAnEntryStandingInForTooManyStocks(): void
    {
        $this->getSourceSalableQuantityData->expects(self::once())
            ->method('execute')
            ->with([])
            ->willReturn([]);

        $entries = $this->addSourceBreakdown->execute([
            'sku-1' => [['manage_stock' => true, 'message' => 'Associated to 3 stocks']],
        ])['sku-1'];

        self::assertArrayNotHasKey('sources', $entries[0]);
    }

    public function testResolvesEveryBreakableSkuInASingleCall(): void
    {
        $this->getSourceSalableQuantityData->expects(self::once())
            ->method('execute')
            ->with(['sku-1', 'sku-2'])
            ->willReturn([]);

        $this->addSourceBreakdown->execute([
            'sku-1' => [$this->stockEntry(2)],
            'sku-2' => [$this->stockEntry(2)],
        ]);
    }

    public function testFlagsWhetherSourceReservationsAreEnabled(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->getSourceSalableQuantityData->method('execute')->willReturn([]);

        $entries = $this->addSourceBreakdown->execute(['sku-1' => [$this->stockEntry(2)]])['sku-1'];

        self::assertTrue($entries[0]['source_reservations_enabled']);
    }

    /**
     * Build an aggregated stock entry as returned by GetSalableQuantityDataBySku.
     *
     * @param int $stockId
     * @return array<string, bool|float|int|string>
     */
    private function stockEntry(int $stockId): array
    {
        return [
            'stock_id' => $stockId,
            'stock_name' => 'Stock ' . $stockId,
            'qty' => 10.0,
            'manage_stock' => true,
        ];
    }

    /**
     * Build a breakdown row as returned by GetSourceSalableQuantityData.
     *
     * @param string $sourceCode
     * @return array<string, bool|float|string>
     */
    private function sourceRow(string $sourceCode): array
    {
        return [
            'source_code' => $sourceCode,
            'source_name' => strtoupper($sourceCode),
            'physical' => 5.0,
            'reserved' => 0.0,
            'salable' => 5.0,
            'source_enabled' => true,
            'source_item_out_of_stock' => false,
        ];
    }
}
