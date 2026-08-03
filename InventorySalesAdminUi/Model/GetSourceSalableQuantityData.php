<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Model;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\SourceReservation\GetSourceAvailabilityBySkus;
use Magento\InventorySalesAdminUi\Model\ResourceModel\GetAssignedStockIdsBySku;

/**
 * Break the salable quantity of the given SKUs down into the sources backing each of their stocks.
 *
 * The rows complement the aggregated salable quantity reported by GetSalableQuantityDataBySku:
 * they show, per source, the physical quantity, the source reservation balance and the resulting
 * salable quantity, so the aggregate can be traced back to the sources it came from.
 */
class GetSourceSalableQuantityData
{
    /**
     * @param GetAssignedStockIdsBySku $getAssignedStockIdsBySku
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     * @param GetSourceAvailabilityBySkus $getSourceAvailability
     */
    public function __construct(
        private readonly GetAssignedStockIdsBySku $getAssignedStockIdsBySku,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock,
        private readonly GetSourceAvailabilityBySkus $getSourceAvailability
    ) {
    }

    /**
     * Get the per-source breakdown of the given SKUs indexed by SKU and stock id.
     *
     * Every SKU and source is resolved in a single availability call, so a grid page costs the
     * same number of availability queries as a single product does.
     *
     * @param string[] $skus
     * @return array<string, array<int, array<int, array<string, bool|float|string>>>>
     */
    public function execute(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        $stockIdsBySku = [];
        $sourcesByStockId = [];
        foreach ($skus as $sku) {
            $stockIds = array_map('intval', $this->getAssignedStockIdsBySku->execute((string) $sku));
            $stockIdsBySku[(string) $sku] = $stockIds;
            $sourcesByStockId = $this->addSourcesOfStocks($stockIds, $sourcesByStockId);
        }

        $availability = $this->getSourceAvailability->execute(
            $skus,
            $this->collectSourceCodes($sourcesByStockId)
        );

        $data = [];
        foreach ($stockIdsBySku as $sku => $stockIds) {
            foreach ($stockIds as $stockId) {
                $rows = $this->buildRows($sourcesByStockId[$stockId], (string) $sku, $availability);
                if ($rows) {
                    $data[$sku][$stockId] = $rows;
                }
            }
        }

        return $data;
    }

    /**
     * Resolve the sources of the given stocks, keeping the ones already resolved.
     *
     * @param int[] $stockIds
     * @param array<int,SourceInterface[]> $sourcesByStockId
     * @return array<int, SourceInterface[]>
     */
    private function addSourcesOfStocks(array $stockIds, array $sourcesByStockId): array
    {
        foreach ($stockIds as $stockId) {
            if (!isset($sourcesByStockId[$stockId])) {
                $sourcesByStockId[$stockId] = $this->getSourcesAssignedToStock->execute($stockId);
            }
        }

        return $sourcesByStockId;
    }

    /**
     * Collect the distinct source codes of every resolved stock.
     *
     * @param array<int,SourceInterface[]> $sourcesByStockId
     * @return string[]
     */
    private function collectSourceCodes(array $sourcesByStockId): array
    {
        $sourceCodes = [];
        foreach ($sourcesByStockId as $sources) {
            foreach ($sources as $source) {
                $sourceCodes[(string) $source->getSourceCode()] = true;
            }
        }

        return array_keys($sourceCodes);
    }

    /**
     * Build the breakdown rows of a SKU on the sources of one stock.
     *
     * Sources the SKU has no source item on are skipped: they hold nothing to report.
     *
     * @param SourceInterface[] $sources
     * @param string $sku
     * @param array<string,array<string,array<string,bool|float>>> $availability
     * @return array<int, array<string, bool|float|string>>
     */
    private function buildRows(array $sources, string $sku, array $availability): array
    {
        $rows = [];
        foreach ($sources as $source) {
            $sourceCode = (string) $source->getSourceCode();
            if (!isset($availability[$sourceCode][$sku])) {
                continue;
            }

            $isEnabled = (bool) $source->isEnabled();
            $sourceAvailability = $availability[$sourceCode][$sku];
            $rows[] = [
                'source_code' => $sourceCode,
                'source_name' => $source->getName() ?: $sourceCode,
                'physical' => (float) $sourceAvailability['physical'],
                'reserved' => (float) $sourceAvailability['reserved'],
                'salable' => $isEnabled ? (float) $sourceAvailability['salable'] : 0.0,
                'source_enabled' => $isEnabled,
                'source_item_out_of_stock' => (bool) $sourceAvailability['source_item_out_of_stock'],
            ];
        }

        return $rows;
    }
}
