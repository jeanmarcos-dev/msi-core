<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Database triggers that record writes against the frozen CatalogInventory tables.
 *
 * MSI stopped reading those tables, so a raw SQL write against them changes nothing and reports
 * no error. The triggers turn that silence into a counter the operator can be warned about.
 */
class LegacyStockWriteTriggers
{
    public const WATCHED_TABLES = [
        'cataloginventory_stock_item',
        'cataloginventory_stock_status',
    ];

    private const LOG_TABLE = 'inventory_legacy_stock_write';

    private const OPERATIONS = ['insert', 'update'];

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Create every detection trigger, replacing any previous version of it.
     *
     * @return void
     */
    public function install(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $logTable = $connection->quoteIdentifier($this->resourceConnection->getTableName(self::LOG_TABLE));

        foreach (self::WATCHED_TABLES as $watchedTable) {
            $realTable = $this->resourceConnection->getTableName($watchedTable);
            foreach (self::OPERATIONS as $operation) {
                $this->drop($connection, $this->triggerName($realTable, $operation));
                $connection->query(
                    sprintf(
                        'CREATE TRIGGER %s AFTER %s ON %s FOR EACH ROW '
                        . 'INSERT INTO %s (table_name, operation, write_count, sample_product_id, '
                        . 'first_detected_at, last_detected_at) VALUES (%s, %s, 1, NEW.product_id, NOW(), NOW()) '
                        . 'ON DUPLICATE KEY UPDATE write_count = write_count + 1, '
                        . 'sample_product_id = NEW.product_id, last_detected_at = NOW()',
                        $connection->quoteIdentifier($this->triggerName($realTable, $operation)),
                        strtoupper($operation),
                        $connection->quoteIdentifier($realTable),
                        $logTable,
                        $connection->quote($watchedTable),
                        $connection->quote($operation)
                    )
                );
            }
        }
    }

    /**
     * Drop every detection trigger.
     *
     * @return void
     */
    public function remove(): void
    {
        $connection = $this->resourceConnection->getConnection();

        foreach (self::WATCHED_TABLES as $watchedTable) {
            $realTable = $this->resourceConnection->getTableName($watchedTable);
            foreach (self::OPERATIONS as $operation) {
                $this->drop($connection, $this->triggerName($realTable, $operation));
            }
        }
    }

    /**
     * Whether every detection trigger is present.
     *
     * @return bool
     */
    public function areInstalled(): bool
    {
        $connection = $this->resourceConnection->getConnection();

        foreach (self::WATCHED_TABLES as $watchedTable) {
            $realTable = $this->resourceConnection->getTableName($watchedTable);
            foreach (self::OPERATIONS as $operation) {
                $found = $connection->fetchOne(
                    'SELECT COUNT(*) FROM information_schema.triggers '
                    . 'WHERE trigger_schema = DATABASE() AND trigger_name = ?',
                    [$this->triggerName($realTable, $operation)]
                );
                if ((int)$found === 0) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Drop a trigger by name when it exists.
     *
     * @param AdapterInterface $connection
     * @param string $triggerName
     * @return void
     */
    private function drop(AdapterInterface $connection, string $triggerName): void
    {
        //phpcs:ignore Magento2.SQL.RawQuery.FoundRawSql
        $connection->query('DROP TRIGGER IF EXISTS ' . $connection->quoteIdentifier($triggerName));
    }

    /**
     * Build the trigger name for a watched table and operation.
     *
     * @param string $realTable
     * @param string $operation
     * @return string
     */
    private function triggerName(string $realTable, string $operation): string
    {
        return $realTable . '_msi_detect_' . $operation;
    }
}
