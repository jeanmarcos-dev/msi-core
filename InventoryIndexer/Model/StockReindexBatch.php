<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model;

/**
 * Collect the stocks to reindex while a stock save is in progress, so they are reindexed once, at its end.
 */
class StockReindexBatch
{
    /**
     * @var int
     */
    private int $depth = 0;

    /**
     * @var int[]
     */
    private array $stockIds = [];

    /**
     * Whether a stock save is in progress.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->depth > 0;
    }

    /**
     * Mark the start of a stock save.
     *
     * @return void
     */
    public function open(): void
    {
        $this->depth++;
    }

    /**
     * Remember stocks to reindex when the stock save ends.
     *
     * @param int[] $stockIds
     * @return void
     */
    public function add(array $stockIds): void
    {
        $this->stockIds = array_values(array_unique(array_merge($this->stockIds, $stockIds)));
    }

    /**
     * Mark the end of a stock save and return the stocks to reindex once the outermost save ends.
     *
     * @return int[]
     */
    public function close(): array
    {
        $this->depth = max(0, $this->depth - 1);
        if ($this->depth > 0) {
            return [];
        }

        $stockIds = $this->stockIds;
        $this->stockIds = [];

        return $stockIds;
    }

    /**
     * Mark the end of a failed stock save and forget what was collected once the outermost save ends.
     *
     * @return void
     */
    public function discard(): void
    {
        $this->close();
    }
}
