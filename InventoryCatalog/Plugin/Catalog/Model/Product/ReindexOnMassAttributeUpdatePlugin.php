<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\Catalog\Model\Product;

use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSourceItemsBySkus;

/**
 * Reindex the MSI stock index after a mass attribute update.
 *
 * Replaces Magento\CatalogInventory\Model\Plugin\ReindexUpdatedProducts, which reindexed the legacy stock
 * index. A mass status change is the common case: it does not touch any source item, so nothing else in the
 * MSI pipeline would notice that the composite salability of the affected parents has to be recomputed.
 */
class ReindexOnMassAttributeUpdatePlugin
{
    /**
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param ReindexSourceItemsBySkus $reindexSourceItemsBySkus
     */
    public function __construct(
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly ReindexSourceItemsBySkus $reindexSourceItemsBySkus
    ) {
    }

    /**
     * Reindex the updated products in every stock that carries them.
     *
     * @param ProductAction $subject
     * @param ProductAction $result
     * @param array $productIds
     * @return ProductAction
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterUpdateAttributes(
        ProductAction $subject,
        ProductAction $result,
        $productIds
    ): ProductAction {
        $productIds = array_unique(array_map('intval', $productIds));
        if ($productIds) {
            $this->reindexSourceItemsBySkus->execute($this->getSkusByProductIds->execute($productIds));
        }

        return $result;
    }
}
