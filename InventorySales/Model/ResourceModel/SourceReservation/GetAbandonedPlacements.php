<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;

/**
 * Find placements that reserved stock and never saved their order.
 */
class GetAbandonedPlacements
{
    private const EPSILON = 0.000001;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get every abandoned placement with its newest reservation id, oldest first.
     *
     * @return array<int, array{increment_id:string, last_id:int}>
     */
    public function execute(): array
    {
        $rows = [];
        foreach ($this->resourceConnection->getConnection()->fetchAll($this->abandoned()) as $row) {
            $rows[] = ['increment_id' => (string)$row['increment_id'], 'last_id' => (int)$row['last_id']];
        }

        return $rows;
    }

    /**
     * Build the increment ids that reserved stock at placement, still hold it, and have no order.
     *
     * @return Select
     */
    private function abandoned(): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $incrementId = sprintf(
            "COALESCE(%s, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.object_increment_id')))",
            ReservationInterface::OBJECT_INCREMENT_ID
        );
        $placed = $connection->quoteInto(
            "SUM(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.event_type')) = ?)",
            SalesEventInterface::EVENT_ORDER_PLACED
        );

        $groups = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                [
                    'increment_id' => $incrementId,
                    'last_id' => 'MAX(' . ReservationInterface::RESERVATION_ID . ')',
                    'balance' => 'SUM(' . ReservationInterface::QUANTITY . ')',
                    'placed' => $placed,
                ]
            )
            ->where($incrementId . ' IS NOT NULL')
            ->where($incrementId . " <> ''")
            ->group([
                'increment_id',
                ReservationInterface::STOCK_ID,
                ReservationInterface::SKU,
                ReservationInterface::SOURCE_CODE,
            ]);

        $placements = $connection->select()
            ->from(['placement' => $groups], ['increment_id', 'last_id' => 'MAX(placement.last_id)'])
            ->group('placement.increment_id')
            ->having('SUM(placement.placed) > 0')
            ->having('MIN(placement.balance) < ?', -self::EPSILON);

        $order = $connection->select()
            ->from(['so' => $this->resourceConnection->getTableName('sales_order')], [new \Zend_Db_Expr('1')])
            ->where('so.increment_id = abandoned.increment_id');

        return $connection->select()
            ->from(['abandoned' => $placements], ['increment_id', 'last_id'])
            ->where('NOT EXISTS (' . $order . ')')
            ->order('abandoned.last_id ASC');
    }
}
