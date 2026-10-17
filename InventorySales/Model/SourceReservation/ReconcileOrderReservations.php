<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationLedger;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderExistsByIncrementId;
use Magento\Sales\Model\Order;

/**
 * Release the reservation demand a terminal order never compensated. For an
 * order in a final state (complete/closed/canceled) the expected net reservation
 * is zero; any remaining negative balance is a missing compensation that pins
 * salable qty below the truth forever (the daily cleanup only deletes zero-sum
 * groups). This appends the missing positive release per (stock, sku, source).
 * It is bounded by the compensation clamp (so it can never over-release even if
 * a late real event also fires) and idempotent (a second run finds no negative
 * balance and does nothing).
 */
class ReconcileOrderReservations
{
    private const TERMINAL_STATES = [Order::STATE_COMPLETE, Order::STATE_CLOSED, Order::STATE_CANCELED];

    /**
     * @param GetOrderReservationLedger $getOrderReservationLedger
     * @param AppendReservationsInterface $appendReservations
     * @param ReservationBuilderInterface $reservationBuilder
     * @param SerializerInterface $serializer
     * @param OrderExistsByIncrementId $orderExistsByIncrementId
     */
    public function __construct(
        private readonly GetOrderReservationLedger $getOrderReservationLedger,
        private readonly AppendReservationsInterface $appendReservations,
        private readonly ReservationBuilderInterface $reservationBuilder,
        private readonly SerializerInterface $serializer,
        private readonly OrderExistsByIncrementId $orderExistsByIncrementId
    ) {
    }

    /**
     * Reconcile a single order, returning the compensations made (or planned when dry-run).
     *
     * @param int $objectId
     * @param string $objectIncrementId
     * @param string $orderState
     * @param bool $dryRun
     * @return array<int, array{stock_id:int, sku:string, source_code:string|null, quantity:float}>
     */
    public function execute(int $objectId, string $objectIncrementId, string $orderState, bool $dryRun = false): array
    {
        if ($objectIncrementId === '' || !in_array($orderState, self::TERMINAL_STATES, true)) {
            return [];
        }

        return $this->releaseLedger('reconciliation', (string)$objectId, $objectIncrementId, null, $dryRun);
    }

    /**
     * Release what a placement that never saved its order reserved up to the cutoff, returning the compensations.
     *
     * @param string $objectIncrementId
     * @param int $cutoff
     * @param bool $dryRun
     * @return array<int, array{stock_id:int, sku:string, source_code:string|null, quantity:float}>
     */
    public function executeAbandoned(string $objectIncrementId, int $cutoff, bool $dryRun = false): array
    {
        if ($objectIncrementId === '' || $this->orderExistsByIncrementId->execute($objectIncrementId)) {
            return [];
        }

        return $this->releaseLedger('abandoned_placement', '', $objectIncrementId, $cutoff, $dryRun);
    }

    /**
     * Append the positive release that brings every negative ledger group of the increment id to zero.
     *
     * @param string $eventType
     * @param string $objectId
     * @param string $objectIncrementId
     * @param int|null $maxReservationId
     * @param bool $dryRun
     * @return array<int, array{stock_id:int, sku:string, source_code:string|null, quantity:float}>
     */
    private function releaseLedger(
        string $eventType,
        string $objectId,
        string $objectIncrementId,
        ?int $maxReservationId,
        bool $dryRun
    ): array {
        $ledger = $this->getOrderReservationLedger->execute($objectIncrementId, $maxReservationId);
        if (empty($ledger)) {
            return [];
        }

        $metadata = $this->serializer->serialize([
            'event_type' => $eventType,
            'object_type' => 'order',
            'object_id' => $objectId,
            'object_increment_id' => $objectIncrementId,
        ]);

        $compensations = [];
        $reservations = [];
        foreach ($ledger as $row) {
            $quantity = -$row['balance'];
            $compensations[] = [
                'stock_id' => $row['stock_id'],
                'sku' => $row['sku'],
                'source_code' => $row['source_code'],
                'quantity' => $quantity,
            ];
            $reservations[] = $this->reservationBuilder
                ->setSku($row['sku'])
                ->setQuantity($quantity)
                ->setStockId($row['stock_id'])
                ->setMetadata($metadata)
                ->setSourceCode($row['source_code'])
                ->setObjectIncrementId($objectIncrementId)
                ->build();
        }

        if (!$dryRun && !empty($reservations)) {
            $this->appendReservations->execute($reservations);
        }

        return $compensations;
    }
}
