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
    private const MAX_SKUS_PER_QUERY = 200;

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
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM);
        $ids = [];
        foreach ($this->groupSkusBySource($keys) as $sourceCode => $skus) {
            foreach (array_chunk($skus, self::MAX_SKUS_PER_QUERY) as $batch) {
                $ids[] = $connection->fetchCol(
                    $connection->select()
                        ->from($table, ['source_item_id'])
                        ->where('source_code = ?', (string)$sourceCode)
                        ->where('sku IN (?)', $batch)
                );
            }
        }

        return array_merge([], ...$ids);
    }

    /**
     * Distinct SKUs of the given keys grouped by source code
     *
     * @param array $keys
     * @return array
     */
    private function groupSkusBySource(array $keys): array
    {
        $skus = [];
        foreach ($keys as $key) {
            $skus[$key['source_code']][$key['sku']] = $key['sku'];
        }

        return array_map('array_values', $skus);
    }
}
