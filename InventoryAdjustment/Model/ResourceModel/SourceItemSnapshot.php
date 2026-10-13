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
    private const MAX_VALUES_PER_QUERY = 200;

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
        $sourceCodes = array_unique(array_map('strval', array_column($keys, 'source_code')));
        $skus = array_unique(array_map('strval', array_column($keys, 'sku')));
        if (count($sourceCodes) > count($skus)) {
            return $this->getIdsGroupedBy($keys, 'sku', 'source_code');
        }

        return $this->getIdsGroupedBy($keys, 'source_code', 'sku');
    }

    /**
     * Ids of the given source items, one query per group value and batch of the other column
     *
     * @param array $keys
     * @param string $groupColumn
     * @param string $otherColumn
     * @return array
     */
    private function getIdsGroupedBy(array $keys, string $groupColumn, string $otherColumn): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM);
        $groups = [];
        foreach ($keys as $key) {
            $groups[(string)$key[$groupColumn]][(string)$key[$otherColumn]] = (string)$key[$otherColumn];
        }

        $ids = [];
        foreach ($groups as $groupValue => $values) {
            foreach (array_chunk(array_values($values), self::MAX_VALUES_PER_QUERY) as $batch) {
                $ids[] = $connection->fetchCol(
                    $connection->select()
                        ->from($table, ['source_item_id'])
                        ->where($groupColumn . ' = ?', (string)$groupValue)
                        ->where($otherColumn . ' IN (?)', $batch)
                );
            }
        }

        return array_merge([], ...$ids);
    }
}
