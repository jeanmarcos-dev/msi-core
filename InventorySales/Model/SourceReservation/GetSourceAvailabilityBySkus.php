<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemDataBySkusAndSources;

/**
 * Resolve the available quantity of the given SKUs at each of the given sources.
 *
 * The available quantity nets the physical source quantity against that source's reservation
 * balance, degrading to the physical quantity when source-level reservations are off. Both the
 * physical quantity and the reservation balance are reported alongside the net result so a caller
 * can show how the net was reached.
 *
 * @api
 */
class GetSourceAvailabilityBySkus
{
    /**
     * @param GetSourceItemDataBySkusAndSources $getSourceItemData
     * @param GetReservationsQuantityBySkusAndSources $getReservationsQuantity
     * @param SourceReservationsConfig $sourceReservationsConfig
     */
    public function __construct(
        private readonly GetSourceItemDataBySkusAndSources $getSourceItemData,
        private readonly GetReservationsQuantityBySkusAndSources $getReservationsQuantity,
        private readonly SourceReservationsConfig $sourceReservationsConfig
    ) {
    }

    /**
     * Get per-source availability indexed by source code and SKU.
     *
     * Resolves in two queries regardless of how many SKUs and sources are requested. Sources
     * without a source item for a SKU are absent from the result.
     *
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return array<string, array<string, array<string, bool|float>>>
     */
    public function execute(array $skus, array $sourceCodes): array
    {
        if (empty($skus) || empty($sourceCodes)) {
            return [];
        }

        $sourceItems = $this->getSourceItemData->execute($skus, $sourceCodes);
        $reservations = $this->sourceReservationsConfig->isEnabled()
            ? $this->getReservationsQuantity->execute($skus, $sourceCodes)
            : [];

        $availability = [];
        foreach ($sourceItems as $sourceCode => $sourceItemsBySku) {
            foreach ($sourceItemsBySku as $sku => $sourceItem) {
                $availability[$sourceCode][$sku] = $this->buildRow(
                    (float) $sourceItem['quantity'],
                    (int) $sourceItem['status'],
                    (float) ($reservations[$sourceCode][$sku] ?? 0.0)
                );
            }
        }

        return $availability;
    }

    /**
     * Build the availability row of a single source item.
     *
     * @param float $physical
     * @param int $status
     * @param float $reserved
     * @return array<string, bool|float>
     */
    private function buildRow(float $physical, int $status, float $reserved): array
    {
        $outOfStock = $status === SourceItemInterface::STATUS_OUT_OF_STOCK;

        return [
            'physical' => $physical,
            'reserved' => $reserved,
            'salable' => $outOfStock ? 0.0 : max(0.0, $physical + $reserved),
            'source_item_out_of_stock' => $outOfStock,
        ];
    }
}
