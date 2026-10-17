<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\FlagManager;

/**
 * The newest reservation id that is old enough for a placement still open below it to be abandoned.
 */
class AbandonedPlacementMark
{
    public const MIN_AGE = 900;
    private const FLAG_CODE = 'inventory_abandoned_placement_mark';

    /**
     * @param FlagManager $flagManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly FlagManager $flagManager,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get the reservation id at or below which an open placement is abandoned, or null when there is none yet.
     *
     * @return int|null
     */
    public function current(): ?int
    {
        $mark = $this->read();
        if ($mark === null) {
            return null;
        }

        return $this->isEffective($mark, $this->tableState()) ? $mark['reservation_id'] : null;
    }

    /**
     * Record the newest reservation id as the next mark, unless the current mark is valid and still too young.
     *
     * @return void
     */
    public function advance(): void
    {
        $mark = $this->read();
        $state = $this->tableState();
        if ($mark !== null && $this->isValid($mark, $state) && !$this->isEffective($mark, $state)) {
            return;
        }

        $this->flagManager->saveFlag(
            self::FLAG_CODE,
            ['reservation_id' => $state['newest'], 'recorded_at' => $state['now']]
        );
    }

    /**
     * Read the stored mark, ignoring anything that is not a well-formed mark.
     *
     * @return array|null
     */
    private function read(): ?array
    {
        $mark = $this->flagManager->getFlagData(self::FLAG_CODE);
        if (!is_array($mark) || !isset($mark['reservation_id'], $mark['recorded_at'])) {
            return null;
        }

        return ['reservation_id' => (int)$mark['reservation_id'], 'recorded_at' => (int)$mark['recorded_at']];
    }

    /**
     * Whether the mark still describes the table: ids and the database clock only move forward past it.
     *
     * @param array $mark
     * @param array $state
     * @return bool
     */
    private function isValid(array $mark, array $state): bool
    {
        return $mark['reservation_id'] <= $state['newest'] && $mark['recorded_at'] <= $state['now'];
    }

    /**
     * Whether the mark is valid and old enough to be used as a cutoff.
     *
     * @param array $mark
     * @param array $state
     * @return bool
     */
    private function isEffective(array $mark, array $state): bool
    {
        return $this->isValid($mark, $state) && $state['now'] - $mark['recorded_at'] >= self::MIN_AGE;
    }

    /**
     * Read the highest reservation id written so far and the database clock in one query.
     *
     * @return array
     */
    private function tableState(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from(
                $this->resourceConnection->getTableName('inventory_reservation'),
                ['newest' => 'MAX(reservation_id)', 'now' => 'UNIX_TIMESTAMP()']
            )
        );

        return ['newest' => (int)($row['newest'] ?? 0), 'now' => (int)($row['now'] ?? 0)];
    }
}
