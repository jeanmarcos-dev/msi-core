<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventoryApi;

use Magento\InventoryApi\Api\Data\StockInterface;
use Magento\InventoryApi\Api\StockRepositoryInterface;
use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventoryIndexer\Model\StockReindexBatch;

/**
 * Reindex the stocks whose websites changed once the stock save, with all its sales channel replacements, is done.
 */
class ReindexAfterStockSavePlugin
{
    /**
     * @param StockReindexBatch $stockReindexBatch
     * @param StockIndexer $stockIndexer
     */
    public function __construct(
        private readonly StockReindexBatch $stockReindexBatch,
        private readonly StockIndexer $stockIndexer
    ) {
    }

    /**
     * Hold the reindex of the stocks until the stock is saved.
     *
     * @param StockRepositoryInterface $subject
     * @param callable $proceed
     * @param StockInterface $stock
     * @return int
     * @throws \Throwable
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSave(StockRepositoryInterface $subject, callable $proceed, StockInterface $stock): int
    {
        $this->stockReindexBatch->open();
        try {
            $stockId = $proceed($stock);
        } catch (\Throwable $exception) {
            $this->stockReindexBatch->discard();
            throw $exception;
        }

        $stockIds = $this->stockReindexBatch->close();
        if ($stockIds) {
            $this->stockIndexer->executeList($stockIds);
        }

        return $stockId;
    }
}
