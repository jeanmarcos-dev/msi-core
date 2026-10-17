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
 * Load the outstanding negative reservation balance of an order per (stock,
 * sku, source), keyed by the increment id (column or metadata). Used by the
 * reconciler to find demand that a terminal order never released.
 */
class GetOrderReservationLedger
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
     * Get the negative balances of an order as a list of rows.
     *
     * @param string $objectIncrementId
     * @param int|null $maxReservationId
     * @return array<int, array{stock_id:int, sku:string, source_code:string|null, balance:float}>
     */
    public function execute(string $objectIncrementId, ?int $maxReservationId = null): array
    {
        if ($objectIncrementId === '') {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $incrementIdExpr = sprintf(
            "COALESCE(%s, JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.object_increment_id')))",
            ReservationInterface::OBJECT_INCREMENT_ID
        );
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                [
                    ReservationInterface::STOCK_ID,
                    ReservationInterface::SKU,
                    ReservationInterface::SOURCE_CODE,
                    'balance' => 'SUM(' . ReservationInterface::QUANTITY . ')',
                ]
            )
            ->where($incrementIdExpr . ' = ?', $objectIncrementId)
            ->group([ReservationInterface::STOCK_ID, ReservationInterface::SKU, ReservationInterface::SOURCE_CODE])
            ->having('SUM(' . ReservationInterface::QUANTITY . ') < ?', -self::EPSILON);
        if ($maxReservationId !== null) {
            $select->where(ReservationInterface::RESERVATION_ID . ' <= ?', $maxReservationId);
        }

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[] = [
                'stock_id' => (int)$row[ReservationInterface::STOCK_ID],
                'sku' => (string)$row[ReservationInterface::SKU],
                'source_code' => $row[ReservationInterface::SOURCE_CODE] !== null
                    ? (string)$row[ReservationInterface::SOURCE_CODE]
                    : null,
                'balance' => (float)$row['balance'],
            ];
        }

        return $rows;
    }
}
