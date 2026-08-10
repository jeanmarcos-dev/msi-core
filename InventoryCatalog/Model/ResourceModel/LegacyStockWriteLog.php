<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Reads and clears the writes recorded against the frozen CatalogInventory tables.
 */
class LegacyStockWriteLog
{
    private const LOG_TABLE = 'inventory_legacy_stock_write';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Every detected write, most recently seen first.
     *
     * @return array
     */
    public function getDetections(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(self::LOG_TABLE))
            ->order('last_detected_at DESC');

        return $connection->fetchAll($select);
    }

    /**
     * Total number of rows written against the frozen tables since the last clear.
     *
     * @return int
     */
    public function getTotalWrites(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(self::LOG_TABLE), ['SUM(write_count)']);

        return (int)$connection->fetchOne($select);
    }

    /**
     * Forget every detection.
     *
     * @return void
     */
    public function clear(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete($this->resourceConnection->getTableName(self::LOG_TABLE));
    }
}
