<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Plugin\InventoryIndexer\Indexer\Stock\Strategy\Sync;

use Magento\InventoryCache\Model\FlushCacheByProductIds;
use Magento\InventoryCache\Model\ResourceModel\GetProductSalabilityByStockIds;
use Magento\InventoryIndexer\Indexer\Stock\Strategy\Sync;

/**
 * Clean cache for specific products after non default stock reindex.
 */
class CacheFlush
{
    /**
     * @param FlushCacheByProductIds $flushCacheByProductIds
     * @param GetProductSalabilityByStockIds $getProductSalabilityByStockIds
     */
    public function __construct(
        private readonly FlushCacheByProductIds $flushCacheByProductIds,
        private readonly GetProductSalabilityByStockIds $getProductSalabilityByStockIds
    ) {
    }

    /**
     * Clean the products whose stock index row appeared, disappeared or changed salability.
     *
     * @param Sync $subject
     * @param callable $proceed
     * @param array $stockIds
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecuteList(Sync $subject, callable $proceed, array $stockIds)
    {
        $before = $this->getProductSalabilityByStockIds->execute($stockIds);
        $proceed($stockIds);
        $after = $this->getProductSalabilityByStockIds->execute($stockIds);

        $productIds = $this->getChangedProductIds($before, $after);
        if ($productIds) {
            $this->flushCacheByProductIds->execute($productIds);
        }
    }

    /**
     * Collect the products whose salability differs between two readings of the stock indexes.
     *
     * @param array $before
     * @param array $after
     * @return int[]
     */
    private function getChangedProductIds(array $before, array $after): array
    {
        $changed = [];
        foreach (array_keys($before + $after) as $stockId) {
            $old = $before[$stockId] ?? [];
            $new = $after[$stockId] ?? [];
            $changed[] = array_keys(array_diff_assoc($old, $new) + array_diff_assoc($new, $old));
        }

        return array_values(array_unique(array_merge([], ...$changed)));
    }
}
