<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventorySales;

use Magento\InventoryIndexer\Indexer\Stock\StockIndexer;
use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;

/**
 * Reindex the stocks a reconcile sweep compensated so their salable qty reflects the restored availability.
 */
class ReindexAfterReconcileReservationsSweepPlugin
{
    /**
     * @param StockIndexer $stockIndexer
     */
    public function __construct(
        private readonly StockIndexer $stockIndexer
    ) {
    }

    /**
     * Reindex the compensated stocks unless the sweep was a dry run.
     *
     * @param ReconcileReservationsSweep $subject
     * @param array $result
     * @param int $limit
     * @param bool $dryRun
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        ReconcileReservationsSweep $subject,
        array $result,
        int $limit,
        bool $dryRun = false
    ): array {
        if (!$dryRun && !empty($result['stock_ids'])) {
            $this->stockIndexer->executeList($result['stock_ids']);
        }

        return $result;
    }
}
