<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\Queue\UpdateIndexSalabilityStatus;

use Magento\InventoryCatalogApi\Model\GetParentSkusOfChildrenSkusInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Indexer\Stock\DataProvider;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;

/**
 * Salability of the parents whose stock index row is computed from their children's rows.
 */
class DerivedParentSalability
{
    /**
     * @param GetParentSkusOfChildrenSkusInterface $getParentSkusOfChildrenSkus
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param GetStockItemsDataInterface $getStockItemsData
     * @param DataProvider $dataProvider
     * @param string[] $derivedTypes
     */
    public function __construct(
        private readonly GetParentSkusOfChildrenSkusInterface $getParentSkusOfChildrenSkus,
        private readonly GetProductTypesBySkusInterface $getProductTypesBySkus,
        private readonly GetStockItemsDataInterface $getStockItemsData,
        private readonly DataProvider $dataProvider,
        private readonly array $derivedTypes = [],
    ) {
    }

    /**
     * Drop the derived parents from salability changes computed without their children.
     *
     * @param array<string,bool> $salabilityChanges
     * @return array<string,bool>
     */
    public function withoutDerivedParents(array $salabilityChanges): array
    {
        if (!$salabilityChanges || !$this->derivedTypes) {
            return $salabilityChanges;
        }

        $types = $this->getProductTypesBySkus->execute(array_map('strval', array_keys($salabilityChanges)));

        return array_filter(
            $salabilityChanges,
            fn ($sku): bool => !in_array($types[$sku] ?? null, $this->derivedTypes, true),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Recompute the derived parents of the given children from their index rows, and return those that changed.
     *
     * @param string[] $childSkus
     * @param int $stockId
     * @return array<string,bool>
     */
    public function getChanges(array $childSkus, int $stockId): array
    {
        $parentSkusByType = $this->getParentSkusByType($childSkus);
        if (!$parentSkusByType) {
            return [];
        }

        $stored = $this->getStockItemsData->execute(array_merge([], ...array_values($parentSkusByType)), $stockId);
        $changes = [];
        foreach ($parentSkusByType as $type => $skus) {
            $computed = $this->dataProvider->getSiblingsData($stockId, $type, $skus);
            foreach ($skus as $sku) {
                $isSalable = (bool)($computed[$sku][IndexStructure::IS_SALABLE] ?? false);
                if ($isSalable !== (bool)($stored[$sku][GetStockItemsDataInterface::IS_SALABLE] ?? false)) {
                    $changes[$sku] = $isSalable;
                }
            }
        }

        return $changes;
    }

    /**
     * Group the derived parents of the given children by product type.
     *
     * @param string[] $childSkus
     * @return array<string,string[]>
     */
    private function getParentSkusByType(array $childSkus): array
    {
        if (!$childSkus || !$this->derivedTypes) {
            return [];
        }

        $parentSkus = array_merge([], ...array_values($this->getParentSkusOfChildrenSkus->execute($childSkus)));
        $parentSkus = array_values(array_unique(array_map('strval', $parentSkus)));
        if (!$parentSkus) {
            return [];
        }

        $parentSkusByType = [];
        foreach ($this->getProductTypesBySkus->execute($parentSkus) as $sku => $type) {
            if (in_array($type, $this->derivedTypes, true)) {
                $parentSkusByType[$type][] = (string)$sku;
            }
        }

        return $parentSkusByType;
    }
}
