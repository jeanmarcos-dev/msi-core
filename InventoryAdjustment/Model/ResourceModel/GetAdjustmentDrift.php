<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;

class GetAdjustmentDrift
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /**
     * Source items whose last adjustment does not match their current quantity
     *
     * @param int $limit
     * @return array
     */
    public function execute(int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $adjustmentTable = $this->resourceConnection->getTableName(AdjustmentWriter::TABLE);
        $lastAdjustments = $connection->select()
            ->from($adjustmentTable, ['adjustment_id' => new Expression('MAX(adjustment_id)')])
            ->where('state = ?', 'available')
            ->group(['source_code', 'sku']);

        $select = $connection->select()
            ->from(['adjustment' => $adjustmentTable], ['source_code', 'sku', 'quantity_after'])
            ->join(['last' => $lastAdjustments], 'last.adjustment_id = adjustment.adjustment_id', [])
            ->joinLeft(
                ['item' => $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM)],
                'item.source_code = adjustment.source_code AND item.sku = adjustment.sku',
                ['quantity' => new Expression('COALESCE(item.quantity, 0)')]
            )
            ->where('COALESCE(item.quantity, 0) <> adjustment.quantity_after')
            ->order(['adjustment.source_code', 'adjustment.sku'])
            ->limit($limit);

        return $connection->fetchAll($select);
    }
}
