<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model;

use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\GetSourceItemIds;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;

/**
 * Rebuild the stock index of every source item a set of skus owns.
 *
 * The indexer is addressed by source item id, so a caller that only knows the sku has no way in
 * other than resolving the source items first. This is that translation, kept in one place.
 */
class ReindexSourceItemsBySkus
{
    /**
     * @var GetSourceItemsBySkuInterface
     */
    private $getSourceItemsBySku;

    /**
     * @var GetSourceItemIds
     */
    private $getSourceItemIds;

    /**
     * @var SourceItemIndexer
     */
    private $sourceItemIndexer;

    /**
     * @param GetSourceItemsBySkuInterface $getSourceItemsBySku
     * @param GetSourceItemIds $getSourceItemIds
     * @param SourceItemIndexer $sourceItemIndexer
     */
    public function __construct(
        GetSourceItemsBySkuInterface $getSourceItemsBySku,
        GetSourceItemIds $getSourceItemIds,
        SourceItemIndexer $sourceItemIndexer
    ) {
        $this->getSourceItemsBySku = $getSourceItemsBySku;
        $this->getSourceItemIds = $getSourceItemIds;
        $this->sourceItemIndexer = $sourceItemIndexer;
    }

    /**
     * Rebuild the stock index of every source item the given skus own.
     *
     * @param string[] $skus
     * @return void
     */
    public function execute(array $skus): void
    {
        if (!$skus) {
            return;
        }

        $sourceItems = [[]];
        foreach ($skus as $sku) {
            $sourceItems[] = $this->getSourceItemsBySku->execute((string)$sku);
        }

        $sourceItemIds = $this->getSourceItemIds->execute(array_merge(...$sourceItems));
        if ($sourceItemIds) {
            $this->sourceItemIndexer->executeList($sourceItemIds);
        }
    }
}
