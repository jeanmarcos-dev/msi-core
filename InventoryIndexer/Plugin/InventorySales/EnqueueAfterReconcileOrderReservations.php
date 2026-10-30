<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Plugin\InventorySales;

use Magento\InventoryIndexer\Model\Queue\EnqueueSalabilityUpdate;
use Magento\InventorySales\Model\SourceReservation\ReconcileOrderReservations;

/**
 * Enqueue the salability recalculation for the stocks whose reservations a reconciliation compensated.
 */
class EnqueueAfterReconcileOrderReservations
{
    /**
     * @param EnqueueSalabilityUpdate $enqueueSalabilityUpdate
     */
    public function __construct(
        private readonly EnqueueSalabilityUpdate $enqueueSalabilityUpdate
    ) {
    }

    /**
     * Enqueue the update after the reservations of a terminal order are released.
     *
     * @param ReconcileOrderReservations $subject
     * @param array $result
     * @param int $objectId
     * @param string $objectIncrementId
     * @param string $orderState
     * @param bool $dryRun
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        ReconcileOrderReservations $subject,
        array $result,
        int $objectId,
        string $objectIncrementId,
        string $orderState,
        bool $dryRun = false
    ): array {
        return $this->enqueue($result, $dryRun);
    }

    /**
     * Enqueue the update after the reservations of an abandoned placement are released.
     *
     * @param ReconcileOrderReservations $subject
     * @param array $result
     * @param string $objectIncrementId
     * @param int $cutoff
     * @param bool $dryRun
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecuteAbandoned(
        ReconcileOrderReservations $subject,
        array $result,
        string $objectIncrementId,
        int $cutoff,
        bool $dryRun = false
    ): array {
        return $this->enqueue($result, $dryRun);
    }

    /**
     * Publish one update per compensated stock unless it is a dry run.
     *
     * @param array $compensations
     * @param bool $dryRun
     * @return array
     */
    private function enqueue(array $compensations, bool $dryRun): array
    {
        if ($dryRun) {
            return $compensations;
        }

        $skusByStock = [];
        foreach ($compensations as $compensation) {
            $skusByStock[(int)$compensation['stock_id']][(string)$compensation['sku']] = (string)$compensation['sku'];
        }
        foreach ($skusByStock as $stockId => $skus) {
            $this->enqueueSalabilityUpdate->execute($stockId, array_values($skus));
        }

        return $compensations;
    }
}
