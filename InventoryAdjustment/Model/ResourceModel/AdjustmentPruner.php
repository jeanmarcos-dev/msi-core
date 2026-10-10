<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;

class AdjustmentPruner
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Delete rows created before the cutoff that a later row of the same item and state supersedes
     *
     * @param string $cutoff
     * @param int $batchSize
     * @return int
     */
    public function prune(string $cutoff, int $batchSize): int
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName(AdjustmentWriter::TABLE);
        $deleted = 0;
        do {
            $ids = $connection->fetchCol($this->selectExpired($connection, $table, $cutoff, $batchSize));
            if ($ids === []) {
                break;
            }
            $deleted += $connection->delete($table, ['adjustment_id IN (?)' => $ids]);
        } while (count($ids) === $batchSize);

        return $deleted;
    }

    /**
     * Select one batch of prunable row ids
     *
     * @param AdapterInterface $connection
     * @param string $table
     * @param string $cutoff
     * @param int $batchSize
     * @return Select
     */
    private function selectExpired(
        AdapterInterface $connection,
        string $table,
        string $cutoff,
        int $batchSize
    ): Select {
        $laterRow = $connection->select()
            ->from(['later' => $table], ['adjustment_id'])
            ->where('later.source_code = adjustment.source_code')
            ->where('later.sku = adjustment.sku')
            ->where('later.state = adjustment.state')
            ->where('later.adjustment_id > adjustment.adjustment_id');

        return $connection->select()
            ->from(['adjustment' => $table], ['adjustment_id'])
            ->where('adjustment.created_at < ?', $cutoff)
            ->where('EXISTS (' . $laterRow . ')')
            ->order('adjustment.adjustment_id')
            ->limit($batchSize);
    }
}
