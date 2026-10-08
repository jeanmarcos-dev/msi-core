<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\ReservationInterface;

/**
 * Orders that still hold reservations at a source, per stock and SKU
 */
class GetPendingReservationsAtSource
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
     * Return the outstanding (negative) balance each order holds at the source for the SKUs
     *
     * @param string[] $skus
     * @param string $sourceCode
     * @return array
     */
    public function execute(array $skus, string $sourceCode): array
    {
        if (empty($skus)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                [
                    ReservationInterface::STOCK_ID,
                    ReservationInterface::SKU,
                    ReservationInterface::OBJECT_INCREMENT_ID,
                    'quantity' => 'SUM(' . ReservationInterface::QUANTITY . ')',
                ]
            )
            ->where(ReservationInterface::SOURCE_CODE . ' = ?', $sourceCode)
            ->where(ReservationInterface::SKU . ' IN (?)', array_map('strval', $skus))
            ->where(ReservationInterface::OBJECT_INCREMENT_ID . ' IS NOT NULL')
            ->group([
                ReservationInterface::STOCK_ID,
                ReservationInterface::SKU,
                ReservationInterface::OBJECT_INCREMENT_ID,
            ])
            ->having('SUM(' . ReservationInterface::QUANTITY . ') < ?', -self::EPSILON)
            ->order([ReservationInterface::OBJECT_INCREMENT_ID, ReservationInterface::STOCK_ID]);

        $rows = $connection->fetchAll($select);
        $orderIds = $this->getOrderIds(array_column($rows, ReservationInterface::OBJECT_INCREMENT_ID));

        $result = [];
        foreach ($rows as $row) {
            $incrementId = (string)$row[ReservationInterface::OBJECT_INCREMENT_ID];
            $result[] = [
                'stock_id' => (int)$row[ReservationInterface::STOCK_ID],
                'sku' => (string)$row[ReservationInterface::SKU],
                'object_increment_id' => $incrementId,
                'object_id' => $orderIds[$incrementId] ?? '',
                'quantity' => (float)$row['quantity'],
            ];
        }

        return $result;
    }

    /**
     * Map order increment ids to entity ids
     *
     * @param string[] $incrementIds
     * @return array
     */
    private function getOrderIds(array $incrementIds): array
    {
        if (empty($incrementIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('sales_order'), ['increment_id', 'entity_id'])
            ->where('increment_id IN (?)', array_values(array_unique($incrementIds)));

        return array_map('strval', $connection->fetchPairs($select));
    }
}
