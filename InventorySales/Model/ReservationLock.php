<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\InventorySales\Model\ResourceModel\AcquireStockItemLocks;

/**
 * Serialise concurrent sales on the SKUs they reserve, for as long as the reservation is being written.
 */
class ReservationLock
{
    /**
     * @param GetSkusRequiringReservation $getSkusRequiringReservation
     * @param AcquireStockItemLocks $acquireStockItemLocks
     */
    public function __construct(
        private readonly GetSkusRequiringReservation $getSkusRequiringReservation,
        private readonly AcquireStockItemLocks $acquireStockItemLocks
    ) {
    }

    /**
     * Lock the SKUs that get a reservation on the stock and return the lock names taken.
     *
     * @param array $skus
     * @param int $stockId
     * @return string[]
     * @throws CouldNotSaveException
     */
    public function acquire(array $skus, int $stockId): array
    {
        $reservedSkus = $this->getSkusRequiringReservation->execute($skus, $stockId);

        return $reservedSkus ? $this->acquireStockItemLocks->execute($reservedSkus, $stockId) : [];
    }

    /**
     * Release the locks taken by acquire().
     *
     * @param string[] $lockNames
     * @return void
     */
    public function release(array $lockNames): void
    {
        if ($lockNames) {
            $this->acquireStockItemLocks->release($lockNames);
        }
    }
}
