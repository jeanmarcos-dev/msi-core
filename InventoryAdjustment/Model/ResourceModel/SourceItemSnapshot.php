<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;

class SourceItemSnapshot
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Read and lock the current quantity and status of the given source items
     *
     * @param array $keys
     * @return array
     */
    public function lock(array $keys): array
    {
        return $this->fetch($keys, true);
    }

    /**
     * Read the current quantity and status of the given source items
     *
     * @param array $keys
     * @return array
     */
    public function read(array $keys): array
    {
        return $this->fetch($keys, false);
    }

    /**
     * Fetch the source items keyed by source code and SKU, locking only rows that exist
     *
     * @param array $keys
     * @param bool $forUpdate
     * @return array
     */
    private function fetch(array $keys, bool $forUpdate): array
    {
        $ids = $this->getSourceItemIds($keys);
        if ($ids === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM),
                ['source_code', 'sku', 'quantity', 'status']
            )
            ->where('source_item_id IN (?)', $ids)
            ->order('source_item_id')
            ->forUpdate($forUpdate);

        $snapshot = [];
        foreach ($connection->fetchAll($select) as $row) {
            $snapshot[$row['source_code']][$row['sku']] = [
                'quantity' => (float)$row['quantity'],
                'status' => (int)$row['status'],
            ];
        }

        return $snapshot;
    }

    /**
     * Ids of the given source items that exist, read without locking
     *
     * @param array $keys
     * @return array
     */
    private function getSourceItemIds(array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        $connection = $this->resourceConnection->getConnection();
        $conditions = [];
        foreach ($keys as $key) {
            $conditions[] = '(' . $connection->quoteInto('source_code = ?', $key['source_code'])
                . ' AND ' . $connection->quoteInto('sku = ?', $key['sku']) . ')';
        }

        return $connection->fetchCol(
            $connection->select()
                ->from(
                    $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM),
                    ['source_item_id']
                )
                ->where(implode(' OR ', $conditions))
        );
    }
}
