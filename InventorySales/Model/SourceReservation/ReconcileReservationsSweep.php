<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetAbandonedPlacements;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetTerminalOrdersWithResidualReservations;
use Psr\Log\LoggerInterface;

/**
 * Reconcile a batch of terminal orders that still carry a residual reservation
 * balance and report the stocks whose availability was restored. Bounded per run;
 * the caller is told when the limit was reached so nothing is silently left behind.
 */
class ReconcileReservationsSweep
{
    /**
     * @param GetTerminalOrdersWithResidualReservations $getTerminalOrdersWithResidualReservations
     * @param ReconcileOrderReservations $reconcileOrderReservations
     * @param GetAbandonedPlacements $getAbandonedPlacements
     * @param AbandonedPlacementMark $abandonedPlacementMark
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly GetTerminalOrdersWithResidualReservations $getTerminalOrdersWithResidualReservations,
        private readonly ReconcileOrderReservations $reconcileOrderReservations,
        private readonly GetAbandonedPlacements $getAbandonedPlacements,
        private readonly AbandonedPlacementMark $abandonedPlacementMark,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run one sweep batch.
     *
     * @param int $limit
     * @param bool $dryRun
     * @return array{orders:int, abandoned:int, abandoned_pending:int, mark_effective:bool, compensations:int,
     *     stock_ids:int[], limit_reached:bool}
     */
    public function execute(int $limit, bool $dryRun = false): array
    {
        $orders = $this->getTerminalOrdersWithResidualReservations->execute($limit);
        $compensations = $this->releaseTerminalOrders($orders, $dryRun);

        $cutoff = $this->abandonedPlacementMark->current();
        [$eligible, $pending] = $this->splitAbandonedPlacements($cutoff);

        $abandoned = 0;
        foreach (array_slice($eligible, 0, $limit) as $incrementId) {
            $made = $this->releaseAbandoned($incrementId, (int)$cutoff, $dryRun);
            if ($made) {
                $abandoned++;
                array_push($compensations, ...$made);
            }
        }

        $stockIds = array_values(array_unique(array_column($compensations, 'stock_id')));
        if (!$dryRun) {
            $this->abandonedPlacementMark->advance();
        }

        return [
            'orders' => count($orders),
            'abandoned' => $abandoned,
            'abandoned_pending' => $pending,
            'mark_effective' => $cutoff !== null,
            'compensations' => count($compensations),
            'stock_ids' => $stockIds,
            'limit_reached' => count($orders) >= $limit || count($eligible) > $limit,
        ];
    }

    /**
     * Reconcile each terminal order, returning all the compensations made.
     *
     * @param array $orders
     * @param bool $dryRun
     * @return array
     */
    private function releaseTerminalOrders(array $orders, bool $dryRun): array
    {
        $compensations = [];
        foreach ($orders as $order) {
            array_push($compensations, ...$this->reconcileOrderReservations->execute(
                $order['object_id'],
                $order['increment_id'],
                $order['state'],
                $dryRun
            ));
        }

        return $compensations;
    }

    /**
     * Split the abandoned placements into those at or below the cutoff, oldest first, and a count of the rest.
     *
     * @param int|null $cutoff
     * @return array{0:string[], 1:int}
     */
    private function splitAbandonedPlacements(?int $cutoff): array
    {
        $eligible = [];
        $pending = 0;
        foreach ($this->getAbandonedPlacements->execute() as $placement) {
            if ($cutoff !== null && $placement['last_id'] <= $cutoff) {
                $eligible[] = $placement['increment_id'];
            } else {
                $pending++;
            }
        }

        return [$eligible, $pending];
    }

    /**
     * Release one abandoned placement, logging and skipping it when its compensation cannot be written.
     *
     * @param string $incrementId
     * @param int $cutoff
     * @param bool $dryRun
     * @return array<int, array{stock_id:int, sku:string, source_code:string|null, quantity:float}>
     */
    private function releaseAbandoned(string $incrementId, int $cutoff, bool $dryRun): array
    {
        try {
            return $this->reconcileOrderReservations->executeAbandoned($incrementId, $cutoff, $dryRun);
        } catch (\Exception $e) {
            $this->logger->warning(
                'Source-level reservations: could not release an abandoned placement.',
                ['increment_id' => $incrementId, 'exception' => $e->getMessage()]
            );
            return [];
        }
    }
}
