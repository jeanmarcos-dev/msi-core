<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Model;

use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;

/**
 * Attach the per-source breakdown to the aggregated salable quantity entries of a set of SKUs.
 *
 * Shared by the product form and the product grid so both surfaces agree on which entries carry a
 * breakdown and resolve it in a single batch, whether they render one product or a whole page.
 */
class AddSourceSalableQuantityBreakdown
{
    /**
     * @param GetSourceSalableQuantityData $getSourceSalableQuantityData
     * @param IsSingleSourceModeInterface $isSingleSourceMode
     * @param SourceReservationsConfig $sourceReservationsConfig
     */
    public function __construct(
        private readonly GetSourceSalableQuantityData $getSourceSalableQuantityData,
        private readonly IsSingleSourceModeInterface $isSingleSourceMode,
        private readonly SourceReservationsConfig $sourceReservationsConfig
    ) {
    }

    /**
     * Add the breakdown to every stock entry that reports a quantity of its own.
     *
     * With a single source the breakdown would only repeat the aggregate, so the entries are
     * returned untouched and nothing is queried.
     *
     * @param array<string,array<int,array<string,mixed>>> $stockEntriesBySku
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function execute(array $stockEntriesBySku): array
    {
        if ($this->isSingleSourceMode->execute() === true) {
            return $stockEntriesBySku;
        }

        $breakdown = $this->getSourceSalableQuantityData->execute(
            $this->collectBreakableSkus($stockEntriesBySku)
        );
        $sourceReservationsEnabled = $this->sourceReservationsConfig->isEnabled();

        foreach ($stockEntriesBySku as $sku => $stockEntries) {
            foreach ($stockEntries as $key => $stockEntry) {
                if (!$this->isBreakable($stockEntry)) {
                    continue;
                }

                $stockId = (int) $stockEntry['stock_id'];
                $stockEntriesBySku[$sku][$key]['sources'] = $breakdown[$sku][$stockId] ?? [];
                $stockEntriesBySku[$sku][$key]['source_reservations_enabled'] = $sourceReservationsEnabled;
            }
        }

        return $stockEntriesBySku;
    }

    /**
     * Collect the SKUs carrying at least one stock entry worth breaking down.
     *
     * @param array<string,array<int,array<string,mixed>>> $stockEntriesBySku
     * @return string[]
     */
    private function collectBreakableSkus(array $stockEntriesBySku): array
    {
        $skus = [];
        foreach ($stockEntriesBySku as $sku => $stockEntries) {
            foreach ($stockEntries as $stockEntry) {
                if ($this->isBreakable($stockEntry)) {
                    $skus[] = (string) $sku;
                    break;
                }
            }
        }

        return $skus;
    }

    /**
     * Check whether a stock entry reports a quantity that can be broken down by source.
     *
     * Entries standing in for too many stocks carry a message instead of a stock, and entries
     * without managed stock report no quantity at all.
     *
     * @param array<string,mixed> $stockEntry
     * @return bool
     */
    private function isBreakable(array $stockEntry): bool
    {
        return isset($stockEntry['stock_id']) && !empty($stockEntry['manage_stock']);
    }
}
