<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Adapter\LockWaitException;

class OrderBalanceGuard
{
    private const TABLE = 'inventory_reservation_order_balance';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Lock each group's balance row until the transaction ends and return its committed balance by group key.
     *
     * @param array $groups
     * @return array
     */
    public function lock(array $groups): array
    {
        $keys = [];
        foreach ($groups as $key => $group) {
            $keys[$key] = $this->rowKey($group);
        }
        asort($keys, SORT_STRING);

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $balances = [];
        foreach (array_keys($keys) as $key) {
            $group = $groups[$key];
            try {
                $connection->insertOnDuplicate(
                    $table,
                    [$this->row($group, (float)$group['ledger'])],
                    ['balance' => new \Zend_Db_Expr('balance')]
                );
                $select = $connection->select()
                    ->from($table, ['balance'])
                    ->where('object_increment_id = ?', $group['object_increment_id'])
                    ->where('stock_id = ?', (int)$group['stock_id'])
                    ->where('sku = ?', $group['sku'])
                    ->where('source_code = ?', $group['source_code'])
                    ->forUpdate(true);
                $balances[$key] = (float)$connection->fetchOne($select);
            } catch (LockWaitException) {
                $balances[$key] = null;
            }
        }

        return $balances;
    }

    /**
     * Add the quantities written for each group to its balance row, creating the rows that do not exist.
     *
     * @param array $rows
     * @return void
     */
    public function add(array $rows): void
    {
        $byKey = [];
        foreach ($rows as $row) {
            $key = $this->rowKey($row);
            $quantity = ($byKey[$key]['balance'] ?? 0.0) + (float)$row['quantity'];
            $byKey[$key] = $this->row($row, $quantity);
        }
        if (!$byKey) {
            return;
        }
        ksort($byKey, SORT_STRING);

        $this->resourceConnection->getConnection()->insertOnDuplicate(
            $this->resourceConnection->getTableName(self::TABLE),
            array_values($byKey),
            ['balance' => new \Zend_Db_Expr('balance + VALUES(balance)')]
        );
    }

    /**
     * Delete the balance rows that are back to zero, in batches by key, and return how many were deleted.
     *
     * @param int $batchSize
     * @return int
     */
    public function deleteSettled(int $batchSize = 1000): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(self::TABLE);
        $deleted = 0;
        do {
            $keys = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['object_increment_id', 'stock_id', 'sku', 'source_code'])
                    ->where('balance = 0')
                    ->limit($batchSize)
            );
            if (!$keys) {
                break;
            }
            $batch = $connection->delete($table, ['balance = 0', $this->matchAnyKey($connection, $keys)]);
            $deleted += $batch;
        } while ($batch > 0 && count($keys) >= $batchSize);

        return $deleted;
    }

    /**
     * Primary key of the balance row of a group or written row, in lock order.
     *
     * @param array $group
     * @return string
     */
    private function rowKey(array $group): string
    {
        return json_encode([
            (string)$group['object_increment_id'],
            (int)$group['stock_id'],
            (string)$group['sku'],
            (string)$group['source_code'],
        ]);
    }

    /**
     * Condition that matches any of the given balance row keys.
     *
     * @param AdapterInterface $connection
     * @param array $keys
     * @return string
     */
    private function matchAnyKey(AdapterInterface $connection, array $keys): string
    {
        $conditions = [];
        foreach ($keys as $key) {
            $conditions[] = sprintf(
                '(%s AND %s AND %s AND %s)',
                $connection->quoteInto('object_increment_id = ?', $key['object_increment_id']),
                $connection->quoteInto('stock_id = ?', (int)$key['stock_id']),
                $connection->quoteInto('sku = ?', $key['sku']),
                $connection->quoteInto('source_code = ?', $key['source_code'])
            );
        }

        return implode(' OR ', $conditions);
    }

    /**
     * Balance row of a group.
     *
     * @param array $group
     * @param float $balance
     * @return array
     */
    private function row(array $group, float $balance): array
    {
        return [
            'object_increment_id' => (string)$group['object_increment_id'],
            'stock_id' => (int)$group['stock_id'],
            'sku' => (string)$group['sku'],
            'source_code' => (string)$group['source_code'],
            'balance' => $balance,
        ];
    }
}
