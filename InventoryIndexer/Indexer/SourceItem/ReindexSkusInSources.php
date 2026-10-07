<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Indexer\SourceItem;

use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\StockSourceLink as StockSourceLinkResourceModel;
use Magento\Inventory\Model\StockSourceLink;
use Magento\InventoryIndexer\Indexer\Stock\SkuListsProcessor;

/**
 * Reindex skus in every stock fed by the given sources, running the salability change processors
 */
class ReindexSkusInSources
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param SkuListInStockFactory $skuListInStockFactory
     * @param SkuListsProcessor $skuListsProcessor
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly SkuListInStockFactory $skuListInStockFactory,
        private readonly SkuListsProcessor $skuListsProcessor
    ) {
    }

    /**
     * Reindex the skus in the stocks linked to the sources, also when their source items no longer exist
     *
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return void
     */
    public function execute(array $skus, array $sourceCodes): void
    {
        $skus = array_values(array_unique($skus));
        $sourceCodes = array_values(array_unique($sourceCodes));
        if (!$skus || !$sourceCodes) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $stockIds = $connection->fetchCol(
            $connection->select()
                ->distinct()
                ->from(
                    $this->resourceConnection->getTableName(StockSourceLinkResourceModel::TABLE_NAME_STOCK_SOURCE_LINK),
                    [StockSourceLink::STOCK_ID]
                )
                ->where(StockSourceLink::SOURCE_CODE . ' IN (?)', $sourceCodes)
        );

        $skuList = array_combine($skus, $skus);
        $skuListInStockList = [];
        foreach ($stockIds as $stockId) {
            $skuListInStockList[] = $this->skuListInStockFactory->create(
                ['stockId' => (int)$stockId, 'skuList' => $skuList]
            );
        }
        if ($skuListInStockList) {
            $this->skuListsProcessor->reindexList($skuListInStockList);
        }
    }
}
