<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Cron;

use Magento\InventorySales\Model\SourceReservation\ReconcileReservationsSweep;
use Magento\InventorySales\Model\SourceReservation\ReconciliationConfig;
use Psr\Log\LoggerInterface;

/**
 * Periodic out-of-band reconciliation sweep. Opt-in; the schedule is read from a
 * configurable cron expression. Bounded per run to keep the job predictable; a
 * hit limit is logged so a growing backlog is visible instead of silently
 * truncated.
 */
class ReconcileReservations
{
    private const BATCH_LIMIT = 500;

    /**
     * @param ReconciliationConfig $reconciliationConfig
     * @param ReconcileReservationsSweep $reconcileReservationsSweep
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ReconciliationConfig $reconciliationConfig,
        private readonly ReconcileReservationsSweep $reconcileReservationsSweep,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Run the sweep when enabled.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->reconciliationConfig->isSweepEnabled()) {
            return;
        }

        $result = $this->reconcileReservationsSweep->execute(self::BATCH_LIMIT);
        if ($result['orders'] > 0) {
            $this->logger->info(
                'Source-level reservations: reconciliation sweep healed residual reservations.',
                ['orders' => $result['orders'], 'compensations' => $result['compensations']]
            );
        }
        if ($result['abandoned'] > 0) {
            $this->logger->info(
                'Source-level reservations: reconciliation sweep released abandoned placements.',
                ['abandoned' => $result['abandoned']]
            );
        }
        if ($result['mark_effective'] && $result['abandoned_pending'] > 0) {
            $this->logger->notice(
                'Source-level reservations: abandoned placements too recent to repair are left for the next run.',
                ['abandoned_pending' => $result['abandoned_pending']]
            );
        }
        if ($result['limit_reached']) {
            $this->logger->warning(
                'Source-level reservations: reconciliation sweep hit its batch limit; residues remain.',
                ['limit' => self::BATCH_LIMIT]
            );
        }
    }
}
