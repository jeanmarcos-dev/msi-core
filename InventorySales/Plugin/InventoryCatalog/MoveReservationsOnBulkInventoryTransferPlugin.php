<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryCatalog;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;
use Magento\InventorySales\Model\SourceReservation\MoveSourceReservations;

/**
 * Move the reservations open orders hold at the origin together with its stock, under the checkout locks
 */
class MoveReservationsOnBulkInventoryTransferPlugin
{
    /**
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param AcquireStockItemLocks $acquireStockItemLocks
     * @param ResourceConnection $resourceConnection
     * @param MoveSourceReservations $moveSourceReservations
     */
    public function __construct(
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly AcquireStockItemLocks $acquireStockItemLocks,
        private readonly ResourceConnection $resourceConnection,
        private readonly MoveSourceReservations $moveSourceReservations
    ) {
    }

    /**
     * Move the reservations and the stock in one transaction, or neither
     *
     * @param BulkInventoryTransfer $subject
     * @param callable $proceed
     * @param array $skus
     * @param string $originSource
     * @param string $destinationSource
     * @param bool $unassignFromOrigin
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function aroundExecute(
        BulkInventoryTransfer $subject,
        callable $proceed,
        array $skus,
        string $originSource,
        string $destinationSource,
        bool $unassignFromOrigin
    ): void {
        if (!$this->sourceReservationsConfig->isEnabled()) {
            $proceed($skus, $originSource, $destinationSource, $unassignFromOrigin);
            return;
        }

        $this->acquireStockItemLocks->executeForSources($skus, [$originSource, $destinationSource]);
        $connection = $this->resourceConnection->getConnection();
        try {
            $connection->beginTransaction();
            try {
                $this->moveSourceReservations->execute($skus, $originSource, $destinationSource);
                $proceed($skus, $originSource, $destinationSource, $unassignFromOrigin);
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollBack();
                throw $e;
            }
        } finally {
            $this->acquireStockItemLocks->releaseAll();
        }
    }
}
