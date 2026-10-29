<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\InventoryApi;

use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;

/**
 * Drop the stock registry snapshot of the products whose source items changed.
 *
 * The legacy registry is served from MSI now and caches what it read for the rest of the request. Saving
 * or deleting a source item is the native way to change that data, so without this every legacy reader
 * that already looked at the product would keep answering with the quantity from before the write.
 */
abstract class AbstractDropStockRegistrySnapshotPlugin
{
    /**
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param StockRegistryStorage $stockRegistryStorage
     */
    public function __construct(
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly StockRegistryStorage $stockRegistryStorage
    ) {
    }

    /**
     * Drop the cached legacy stock registry entries of every sku the write touched.
     *
     * @param SourceItemInterface[] $sourceItems
     * @return void
     */
    protected function dropSnapshot(array $sourceItems): void
    {
        $skus = [];
        foreach ($sourceItems as $sourceItem) {
            $skus[$sourceItem->getSku()] = $sourceItem->getSku();
        }
        if (!$skus) {
            return;
        }

        try {
            $productIds = $this->getProductIdsBySkus->execute(array_values($skus));
        } catch (LocalizedException $someSkuIsNotInTheCatalog) {
            // No id to key the removal by, so the whole snapshot goes. It is a per-request read cache and
            // the alternative is serving stale quantities.
            $this->stockRegistryStorage->clean();

            return;
        }

        foreach ($productIds as $productId) {
            $this->stockRegistryStorage->removeStockItem((int) $productId);
            $this->stockRegistryStorage->removeStockStatus((int) $productId);
        }
    }
}
