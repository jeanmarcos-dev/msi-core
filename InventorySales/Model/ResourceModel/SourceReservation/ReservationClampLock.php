<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\InventorySales\Model\ResourceModel\InventoryLockName;

/**
 * Advisory locks that serialise concurrent compensation writes for the same
 * (stock, sku), so two simultaneous releases of one order cannot both read the
 * same outstanding balance and together over-release it. Stateless: the caller
 * owns the returned lock names and releases exactly them, so it never interferes
 * with locks held elsewhere on the connection (which are re-entrant per session).
 * Names are globally ordered, so acquiring several at once cannot deadlock.
 */
class ReservationClampLock
{
    private const LOCK_TIMEOUT = 10;

    /**
     * @param ResourceConnection $resourceConnection
     * @param InventoryLockName $inventoryLockName
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly InventoryLockName $inventoryLockName
    ) {
    }

    /**
     * Acquire one lock per distinct (stock, sku) and return the acquired names.
     *
     * @param array $items
     * @return string[]
     */
    public function acquire(array $items): array
    {
        $names = $this->lockNames($items);
        if (empty($names)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $acquired = [];
        foreach ($names as $name) {
            $connection->fetchOne('SELECT GET_LOCK(?, ?)', [$name, self::LOCK_TIMEOUT]);
            $acquired[] = $name;
        }

        return $acquired;
    }

    /**
     * Release the given lock names.
     *
     * @param string[] $names
     * @return void
     */
    public function release(array $names): void
    {
        if (empty($names)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        foreach ($names as $name) {
            try {
                $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$name]);
            } catch (\Throwable $e) { //phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch
                // Locks are released by MySQL when the connection closes.
            }
        }
    }

    /**
     * Build the globally-ordered, de-duplicated lock names for the items.
     *
     * @param array $items
     * @return string[]
     */
    private function lockNames(array $items): array
    {
        $names = [];
        foreach ($items as $item) {
            $names[] = $this->inventoryLockName->build(
                InventoryLockName::CLAMP,
                (string)(int)$item['stock_id'],
                (string)$item['sku']
            );
        }
        $names = array_values(array_unique($names));
        sort($names, SORT_STRING);

        return $names;
    }
}
