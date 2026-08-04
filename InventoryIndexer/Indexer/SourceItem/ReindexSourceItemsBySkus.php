<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Indexer\SourceItem;

use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;

/**
 * Reindex the given SKUs in every stock that carries them.
 *
 * Reindexing goes through the source items of each SKU rather than through a stock id, because that is what
 * expands the sku list to the composite parents. Callers that only save the default source item refresh
 * stock 1 alone, which is the divergence this service exists to avoid.
 */
class ReindexSourceItemsBySkus
{
    /**
     * @param GetSourceItemsBySkuInterface $getSourceItemsBySku
     * @param GetSourceItemIds $getSourceItemIds
     * @param SourceItemIndexer $sourceItemIndexer
     */
    public function __construct(
        private readonly GetSourceItemsBySkuInterface $getSourceItemsBySku,
        private readonly GetSourceItemIds $getSourceItemIds,
        private readonly SourceItemIndexer $sourceItemIndexer
    ) {
    }

    /**
     * Reindex the MSI stock index for the given SKUs.
     *
     * @param string[] $skus
     * @return void
     */
    public function execute(array $skus): void
    {
        $sourceItems = [[]];
        foreach ($skus as $sku) {
            $sourceItems[] = $this->getSourceItemsBySku->execute($sku);
        }
        $sourceItems = array_merge(...$sourceItems);
        if (!$sourceItems) {
            return;
        }

        $sourceItemIds = $this->getSourceItemIds->execute($sourceItems);
        if ($sourceItemIds) {
            $this->sourceItemIndexer->executeList($sourceItemIds);
        }
    }
}
