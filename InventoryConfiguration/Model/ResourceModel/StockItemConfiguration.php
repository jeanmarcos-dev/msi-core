<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * Read and write access to the MSI stock item configuration table.
 */
class StockItemConfiguration
{
    public const TABLE_NAME = 'inventory_stock_item_configuration';

    public const SKU = 'sku';

    /**
     * Every column of the table except the primary key.
     */
    public const FIELDS = [
        'min_qty',
        'use_config_min_qty',
        'is_qty_decimal',
        'backorders',
        'use_config_backorders',
        'min_sale_qty',
        'use_config_min_sale_qty',
        'max_sale_qty',
        'use_config_max_sale_qty',
        'is_in_stock',
        'low_stock_date',
        'notify_stock_qty',
        'use_config_notify_stock_qty',
        'manage_stock',
        'use_config_manage_stock',
        'stock_status_changed_auto',
        'use_config_qty_increments',
        'qty_increments',
        'use_config_enable_qty_inc',
        'enable_qty_increments',
        'is_decimal_divided',
    ];

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Load configuration rows indexed by sku.
     *
     * @param string[] $skus
     * @return array<string,array<string,mixed>>
     */
    public function get(array $skus): array
    {
        if (empty($skus)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(self::TABLE_NAME))
            ->where(self::SKU . ' IN (?)', $skus);

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(string)$row[self::SKU]] = $row;
        }

        return $rows;
    }

    /**
     * Insert or update configuration rows. Each row must contain the sku key.
     *
     * Columns a row omits keep their table default on insert and their stored value on update, so
     * rows are grouped by the set of columns they carry and written one group per statement.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return void
     */
    public function save(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $groups = [];
        foreach ($rows as $row) {
            $columns = array_keys($row);
            sort($columns);
            $groups[implode(',', $columns)][] = $row;
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName(self::TABLE_NAME);
        foreach ($groups as $group) {
            $connection->insertOnDuplicate(
                $tableName,
                array_values($group),
                array_values(array_diff(array_keys(reset($group)), [self::SKU]))
            );
        }
    }

    /**
     * Remove the configuration rows of the given skus.
     *
     * @param string[] $skus
     * @return void
     */
    public function deleteBySkus(array $skus): void
    {
        if (empty($skus)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            $this->resourceConnection->getTableName(self::TABLE_NAME),
            [self::SKU . ' IN (?)' => $skus]
        );
    }

    /**
     * Update the given fields for every listed sku.
     *
     * @param string[] $skus
     * @param array<string,mixed> $data
     * @return void
     */
    public function updateBySkus(array $skus, array $data): void
    {
        if (empty($skus) || empty($data)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName(self::TABLE_NAME),
            $data,
            [self::SKU . ' IN (?)' => $skus]
        );
    }
}
