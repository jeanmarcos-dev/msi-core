<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventorySalesApi;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Model\StockReindexBatch;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Model\ReplaceSalesChannelsForStockInterface;

/**
 * Reindex the stocks whose websites change, because the rows of composite products depend on those websites.
 */
class ReindexAfterSalesChannelsReplacePlugin
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param StockIndexer $stockIndexer
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param IndexerRegistry $indexerRegistry
     * @param StockReindexBatch $stockReindexBatch
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StockIndexer $stockIndexer,
        private readonly DefaultStockProviderInterface $defaultStockProvider,
        private readonly IndexerRegistry $indexerRegistry,
        private readonly StockReindexBatch $stockReindexBatch
    ) {
    }

    /**
     * Reindex the stocks that gain or lose a website once the channels are replaced, or once the stock save ends.
     *
     * @param ReplaceSalesChannelsForStockInterface $subject
     * @param callable $proceed
     * @param SalesChannelInterface[] $salesChannels
     * @param int $stockId
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        ReplaceSalesChannelsForStockInterface $subject,
        callable $proceed,
        array $salesChannels,
        int $stockId
    ): void {
        if (!$this->indexerRegistry->get(InventoryIndexer::INDEXER_ID)->isValid()) {
            $proceed($salesChannels, $stockId);
            return;
        }

        $websiteCodes = $this->getWebsiteCodes($salesChannels);
        $stockIdsBefore = $this->getStockIdsByWebsiteCode($websiteCodes, $stockId);
        $proceed($salesChannels, $stockId);

        $affectedStockIds = $this->getAffectedStockIds($websiteCodes, $stockIdsBefore, $stockId);
        if (!$affectedStockIds) {
            return;
        }

        if ($this->stockReindexBatch->isOpen()) {
            $this->stockReindexBatch->add($affectedStockIds);
            return;
        }

        $this->stockIndexer->executeList($affectedStockIds);
    }

    /**
     * Get the codes of the website channels.
     *
     * @param SalesChannelInterface[] $salesChannels
     * @return string[]
     */
    private function getWebsiteCodes(array $salesChannels): array
    {
        $codes = [];
        foreach ($salesChannels as $salesChannel) {
            if ($salesChannel->getType() === SalesChannelInterface::TYPE_WEBSITE) {
                $codes[] = $salesChannel->getCode();
            }
        }

        return $codes;
    }

    /**
     * Get the stock each website of the given codes or of the given stock is assigned to.
     *
     * @param string[] $websiteCodes
     * @param int $stockId
     * @return array
     */
    private function getStockIdsByWebsiteCode(array $websiteCodes, int $stockId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('inventory_stock_sales_channel'), ['code', 'stock_id'])
            ->where('type = ?', SalesChannelInterface::TYPE_WEBSITE)
            ->where(
                '(' . $connection->quoteInto('stock_id = ?', $stockId)
                . ' OR ' . $connection->quoteInto('code IN (?)', $websiteCodes ?: ['']) . ')'
            );

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Get the stocks that gain or lose a website with the replacement.
     *
     * @param string[] $websiteCodes
     * @param int[] $stockIdsBefore
     * @param int $stockId
     * @return int[]
     */
    private function getAffectedStockIds(array $websiteCodes, array $stockIdsBefore, int $stockId): array
    {
        $previousCodes = array_keys(array_filter($stockIdsBefore, fn (int $id) => $id === $stockId));
        $addedCodes = array_diff($websiteCodes, $previousCodes);
        $removedCodes = array_diff($previousCodes, $websiteCodes);
        if (!$addedCodes && !$removedCodes) {
            return [];
        }

        $affected = [$stockId];
        foreach ($addedCodes as $code) {
            $affected[] = $stockIdsBefore[$code] ?? $stockId;
        }
        if ($removedCodes) {
            $affected[] = (int)$this->defaultStockProvider->getId();
        }

        return array_values(array_unique($affected));
    }
}
