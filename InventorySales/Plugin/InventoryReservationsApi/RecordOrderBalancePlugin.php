<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryReservationsApi;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderBalanceGuard;
use Magento\InventorySales\Model\SourceReservation\ReservationOrderIncrementId;

class RecordOrderBalancePlugin
{
    /**
     * @param OrderBalanceGuard $orderBalanceGuard
     * @param ResourceConnection $resourceConnection
     * @param ReservationOrderIncrementId $reservationOrderIncrementId
     */
    public function __construct(
        private readonly OrderBalanceGuard $orderBalanceGuard,
        private readonly ResourceConnection $resourceConnection,
        private readonly ReservationOrderIncrementId $reservationOrderIncrementId
    ) {
    }

    /**
     * Add every appended reservation of an order to its balance row, in the transaction that writes it.
     *
     * @param AppendReservationsInterface $subject
     * @param callable $proceed
     * @param ReservationInterface[] $reservations
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(AppendReservationsInterface $subject, callable $proceed, array $reservations)
    {
        $rows = $this->balanceRows($reservations);
        if (empty($rows)) {
            return $proceed($reservations);
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $proceed($reservations);
            $this->orderBalanceGuard->add($rows);
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Balance rows of the reservations that belong to an order.
     *
     * @param ReservationInterface[] $reservations
     * @return array<int, array{object_increment_id:string, stock_id:int, sku:string, source_code:string,
     *     quantity:float}>
     */
    private function balanceRows(array $reservations): array
    {
        $rows = [];
        foreach ($reservations as $reservation) {
            $incrementId = $this->reservationOrderIncrementId->execute($reservation);
            if ($incrementId === null) {
                continue;
            }
            $rows[] = [
                'object_increment_id' => $incrementId,
                'stock_id' => (int)$reservation->getStockId(),
                'sku' => $reservation->getSku(),
                'source_code' => (string)$reservation->getSourceCode(),
                'quantity' => (float)$reservation->getQuantity(),
            ];
        }

        return $rows;
    }
}
