<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryReservationsApi;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationBalance;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\OrderBalanceGuard;
use Magento\InventorySales\Model\SourceReservation\ReservationOrderIncrementId;
use Psr\Log\LoggerInterface;

/**
 * Clamp compensation (positive) reservations so the cumulative balance of an
 * order per (sku, source) never becomes positive. A release that exceeds the
 * outstanding negative balance would leave a permanent positive residue (the
 * daily cleanup only deletes zero-sum groups) and inflate salable qty. The
 * excess is dropped and traced. Enforced at the single append chokepoint so it
 * covers every path (shipment, cancel, credit memo, manual compensation), in
 * both stock-scoped and source-aware modes. Rows without a resolvable order
 * increment id pass through unchanged.
 */
class ClampCompensationReservationsPlugin
{
    private const EPSILON = 0.000001;

    /**
     * @param GetOrderReservationBalance $getOrderReservationBalance
     * @param ReservationBuilderInterface $reservationBuilder
     * @param LoggerInterface $logger
     * @param OrderBalanceGuard $orderBalanceGuard
     * @param ResourceConnection $resourceConnection
     * @param ReservationOrderIncrementId $reservationOrderIncrementId
     */
    public function __construct(
        private readonly GetOrderReservationBalance $getOrderReservationBalance,
        private readonly ReservationBuilderInterface $reservationBuilder,
        private readonly LoggerInterface $logger,
        private readonly OrderBalanceGuard $orderBalanceGuard,
        private readonly ResourceConnection $resourceConnection,
        private readonly ReservationOrderIncrementId $reservationOrderIncrementId
    ) {
    }

    /**
     * Clamp positive reservations to the outstanding negative balance before appending.
     *
     * @param AppendReservationsInterface $subject
     * @param callable $proceed
     * @param ReservationInterface[] $reservations
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(AppendReservationsInterface $subject, callable $proceed, array $reservations)
    {
        $groups = $this->orderGroups($reservations);
        if (empty($groups)) {
            return $proceed($reservations);
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $groups = $this->withLedger($groups);
            $started = microtime(true);
            $committed = $this->orderBalanceGuard->lock($groups);
            $waited = microtime(true) - $started;
            $this->assertWholeWhenItCarriesDemand($reservations, $groups, $committed);

            $consumed = [];
            $result = [];
            foreach ($reservations as $reservation) {
                $clamped = $this->clamp($reservation, $groups, $committed, $consumed, $waited);
                if ($clamped !== null) {
                    $result[] = $clamped;
                }
            }

            if (!empty($result)) {
                $proceed($result);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Collect the distinct order groups of a batch that carries a release, keyed by their balance key.
     *
     * @param ReservationInterface[] $reservations
     * @return array
     */
    private function orderGroups(array $reservations): array
    {
        $groups = [];
        $releases = false;
        foreach ($reservations as $reservation) {
            $incrementId = $this->reservationOrderIncrementId->execute($reservation);
            if ($incrementId === null) {
                continue;
            }
            $releases = $releases || $reservation->getQuantity() > 0;
            $groups[$this->balanceKey($incrementId, $reservation)] ??= [
                'object_increment_id' => $incrementId,
                'stock_id' => (int)$reservation->getStockId(),
                'sku' => $reservation->getSku(),
                'source_code' => (string)$reservation->getSourceCode(),
            ];
        }

        return $releases ? $groups : [];
    }

    /**
     * Add to every group the balance its reservations show.
     *
     * @param array $groups
     * @return array
     */
    private function withLedger(array $groups): array
    {
        $orders = [];
        foreach ($groups as $group) {
            $order = $group['object_increment_id'] . '|' . $group['stock_id'];
            $orders[$order]['object_increment_id'] = $group['object_increment_id'];
            $orders[$order]['stock_id'] = $group['stock_id'];
            $orders[$order]['skus'][$group['sku']] = $group['sku'];
        }

        $ledgers = [];
        foreach ($orders as $order => $request) {
            $ledgers[$order] = $this->getOrderReservationBalance->execute(
                $request['object_increment_id'],
                array_values($request['skus']),
                $request['stock_id']
            );
        }

        foreach ($groups as $key => $group) {
            $order = $group['object_increment_id'] . '|' . $group['stock_id'];
            $groups[$key]['ledger'] = (float)($ledgers[$order][$group['sku']][$group['source_code']] ?? 0.0);
        }

        return $groups;
    }

    /**
     * Return the reservation as-is, clamped, or null when it must be dropped.
     *
     * @param ReservationInterface $reservation
     * @param array $groups
     * @param array $committed
     * @param array $consumed
     * @param float $waited
     * @return ReservationInterface|null
     */
    private function clamp(
        ReservationInterface $reservation,
        array $groups,
        array $committed,
        array &$consumed,
        float $waited
    ): ?ReservationInterface {
        $requested = $reservation->getQuantity();
        if ($requested <= 0) {
            return $reservation;
        }
        $incrementId = $this->reservationOrderIncrementId->execute($reservation);
        if ($incrementId === null) {
            return $reservation;
        }

        $key = $this->balanceKey($incrementId, $reservation);
        $sku = $reservation->getSku();
        $sourceKey = (string)$reservation->getSourceCode();

        $balance = $committed[$key] ?? null;
        if ($balance === null) {
            $this->traceUnlocked($incrementId, $reservation, $requested, $waited);
            return null;
        }

        $existing = max($balance, $groups[$key]['ledger']);
        $allowed = max(0.0, -$existing) - ($consumed[$key] ?? 0.0);
        $take = min($requested, max(0.0, $allowed));

        if ($take + self::EPSILON < $requested) {
            $this->traceClamp($incrementId, $sku, $sourceKey, $requested, $take);
        }
        if ($take <= self::EPSILON) {
            return null;
        }
        $consumed[$key] = ($consumed[$key] ?? 0.0) + $take;

        if (abs($take - $requested) <= self::EPSILON) {
            return $reservation;
        }

        return $this->reservationBuilder
            ->setSku($sku)
            ->setQuantity($take)
            ->setStockId($reservation->getStockId())
            ->setMetadata($reservation->getMetadata())
            ->setSourceCode($reservation->getSourceCode())
            ->setObjectIncrementId($reservation->getObjectIncrementId())
            ->build();
    }

    /**
     * Refuse a batch that moves an order's demand when one of its groups could not be locked.
     *
     * @param ReservationInterface[] $reservations
     * @param array $groups
     * @param array $committed
     * @return void
     * @throws CouldNotSaveException
     */
    private function assertWholeWhenItCarriesDemand(array $reservations, array $groups, array $committed): void
    {
        $locked = array_keys(array_filter($committed, static fn (?float $balance) => $balance === null));
        if (!$locked) {
            return;
        }
        foreach ($reservations as $reservation) {
            if ($reservation->getQuantity() < 0
                && $this->reservationOrderIncrementId->execute($reservation) !== null
            ) {
                throw new CouldNotSaveException(__(
                    'The reservation balance of order %1 stayed locked by another operation. Try again.',
                    $groups[$locked[0]]['object_increment_id']
                ));
            }
        }
    }

    /**
     * Balance key of the group a reservation belongs to.
     *
     * @param string $incrementId
     * @param ReservationInterface $reservation
     * @return string
     */
    private function balanceKey(string $incrementId, ReservationInterface $reservation): string
    {
        return json_encode([
            $incrementId,
            (int)$reservation->getStockId(),
            $reservation->getSku(),
            (string)$reservation->getSourceCode(),
        ]);
    }

    /**
     * Log a clamped compensation.
     *
     * @param string $incrementId
     * @param string $sku
     * @param string $sourceKey
     * @param float $requested
     * @param float $granted
     * @return void
     */
    private function traceClamp(
        string $incrementId,
        string $sku,
        string $sourceKey,
        float $requested,
        float $granted
    ): void {
        $this->logger->warning(
            'Source-level reservations: clamped compensation exceeding the outstanding balance.',
            [
                'object_increment_id' => $incrementId,
                'sku' => $sku,
                'source_code' => $sourceKey === '' ? null : $sourceKey,
                'requested' => $requested,
                'granted' => $granted,
            ]
        );
    }

    /**
     * Log a compensation left unwritten because its order balance stayed locked.
     *
     * @param string $incrementId
     * @param ReservationInterface $reservation
     * @param float $requested
     * @param float $waited
     * @return void
     */
    private function traceUnlocked(
        string $incrementId,
        ReservationInterface $reservation,
        float $requested,
        float $waited
    ): void {
        $sourceKey = (string)$reservation->getSourceCode();
        $this->logger->error(
            'Source-level reservations: compensation not written, its order balance stayed locked.',
            [
                'object_increment_id' => $incrementId,
                'stock_id' => (int)$reservation->getStockId(),
                'sku' => $reservation->getSku(),
                'source_code' => $sourceKey === '' ? null : $sourceKey,
                'requested' => $requested,
                'waited_seconds' => round($waited, 3),
            ]
        );
    }
}
