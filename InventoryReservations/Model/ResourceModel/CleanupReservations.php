<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Sql\Expression;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventoryReservationsApi\Model\CleanupReservationsInterface;

/**
 * @inheritdoc
 */
class CleanupReservations implements CleanupReservationsInterface
{
    private const DELETE_CHUNK_SIZE = 10000;

    private const OBJECT_TYPE_KEY = "CAST(JSON_EXTRACT(metadata, '$.object_type') AS CHAR)";

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var int
     */
    private $groupConcatMaxLen;

    /**
     * @param ResourceConnection $resource
     * @param int $groupConcatMaxLen
     */
    public function __construct(
        ResourceConnection $resource,
        int $groupConcatMaxLen
    ) {
        $this->resource = $resource;
        $this->groupConcatMaxLen = $groupConcatMaxLen;
    }

    /**
     * @inheritdoc
     */
    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $seenIds = [];
        $chunk = [];
        foreach (['object_id', 'object_increment_id'] as $field) {
            foreach ($this->getCompensatedGroups($field) as $group) {
                $groupIds = $this->takeUnseenIds($this->getGroupReservationIds($field, $group), $seenIds);
                if (!$groupIds) {
                    continue;
                }
                if ($chunk && count($chunk) + count($groupIds) > self::DELETE_CHUNK_SIZE) {
                    $this->deleteReservations($connection, $chunk);
                    $chunk = [];
                }
                array_push($chunk, ...$groupIds);
            }
        }
        if ($chunk) {
            $this->deleteReservations($connection, $chunk);
        }
    }

    /**
     * Keep the ids not deleted yet by an earlier group
     *
     * @param int[] $reservationIds
     * @param array $seenIds
     * @return int[]
     */
    private function takeUnseenIds(array $reservationIds, array &$seenIds): array
    {
        $unseenIds = [];
        foreach ($reservationIds as $reservationId) {
            if ($reservationId && !isset($seenIds[$reservationId])) {
                $seenIds[$reservationId] = true;
                $unseenIds[] = $reservationId;
            }
        }

        return $unseenIds;
    }

    /**
     * Delete reservations by id
     *
     * @param AdapterInterface $connection
     * @param int[] $reservationIds
     * @return void
     */
    private function deleteReservations(AdapterInterface $connection, array $reservationIds): void
    {
        $connection->delete(
            $this->resource->getTableName('inventory_reservation'),
            [ReservationInterface::RESERVATION_ID . ' IN (?)' => $reservationIds]
        );
    }

    /**
     * Groups of one object, type and source whose reservations add up to zero
     *
     * @param string $field
     * @return array
     */
    private function getCompensatedGroups(string $field): array
    {
        $connection = $this->resource->getConnection();
        $objectKey = $this->getObjectKey($field);
        $select = $connection->select()
            ->from(
                $this->resource->getTableName('inventory_reservation'),
                [
                    'reservation_ids' => 'GROUP_CONCAT(' . ReservationInterface::RESERVATION_ID . ')',
                    'reservation_count' => 'COUNT(*)',
                    'object_key' => new Expression($objectKey),
                    'object_type' => new Expression(self::OBJECT_TYPE_KEY),
                    'source_code' => ReservationInterface::SOURCE_CODE,
                ]
            )
            ->where($objectKey . ' IS NOT NULL')
            ->group([$objectKey, self::OBJECT_TYPE_KEY, ReservationInterface::SOURCE_CODE])
            ->having('SUM(' . ReservationInterface::QUANTITY . ') = 0');
        $connection->query('SET group_concat_max_len = ' . $this->groupConcatMaxLen);

        return $connection->fetchAll($select);
    }

    /**
     * Reservation ids of a group, read again when GROUP_CONCAT truncated the list
     *
     * @param string $field
     * @param array $group
     * @return int[]
     */
    private function getGroupReservationIds(string $field, array $group): array
    {
        $reservationIds = array_map('intval', explode(',', (string)$group['reservation_ids']));
        if (count($reservationIds) === (int)$group['reservation_count']) {
            return $reservationIds;
        }

        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('inventory_reservation'), [ReservationInterface::RESERVATION_ID])
            ->where($this->getObjectKey($field) . ' = ?', $group['object_key']);
        $this->whereNullable($select, self::OBJECT_TYPE_KEY, $group['object_type']);
        $this->whereNullable($select, ReservationInterface::SOURCE_CODE, $group['source_code']);

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Match a column that may be NULL
     *
     * @param Select $select
     * @param string $column
     * @param string|null $value
     * @return void
     */
    private function whereNullable(Select $select, string $column, ?string $value): void
    {
        if ($value === null) {
            $select->where($column . ' IS NULL');
        } else {
            $select->where($column . ' = ?', $value);
        }
    }

    /**
     * Text of a metadata field, so that grouping and matching compare the same value
     *
     * @param string $field
     * @return string
     */
    private function getObjectKey(string $field): string
    {
        return "CAST(JSON_EXTRACT(metadata, '$.$field') AS CHAR)";
    }
}
