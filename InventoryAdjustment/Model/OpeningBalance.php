<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;
use Magento\InventoryAdjustment\Model\ResourceModel\AdjustmentWriter;
use Magento\InventoryAdjustmentApi\Model\ActorType;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Throwable;

class OpeningBalance
{
    public const NOTE = 'opening_balance';
    private const PRECISION = 4;

    /**
     * @param ResourceConnection $resourceConnection
     * @param AdjustmentWriter $writer
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly AdjustmentWriter $writer
    ) {
    }

    /**
     * Write an opening row for every source item with stock and no history, one locked batch at a time
     *
     * @param string $requestId
     * @param int $batchSize
     * @return int
     * @throws Throwable
     */
    public function write(string $requestId, int $batchSize): int
    {
        $connection = $this->resourceConnection->getConnection();
        $written = 0;
        $lastId = 0;
        while ($ids = $connection->fetchCol($this->selectCandidates($connection, $lastId, $batchSize))) {
            $lastId = (int)end($ids);
            $connection->beginTransaction();
            try {
                $connection->fetchCol($this->selectForLock($connection, $ids));
                $items = $connection->fetchAll($this->filterWithoutHistory($connection, $this->selectItems($ids)));
                if ($items !== []) {
                    $this->writer->write($this->buildRows($items, $requestId));
                }
                $connection->commit();
            } catch (Throwable $exception) {
                $connection->rollBack();
                throw $exception;
            }
            $written += count($items);
        }

        return $written;
    }

    /**
     * Select, without locking, the ids of the next batch of source items with stock and no history
     *
     * @param AdapterInterface $connection
     * @param int $lastId
     * @param int $batchSize
     * @return Select
     */
    private function selectCandidates(AdapterInterface $connection, int $lastId, int $batchSize): Select
    {
        return $this->filterWithoutHistory(
            $connection,
            $connection->select()
                ->from(['item' => $this->getSourceItemTable()], ['source_item_id'])
                ->where('item.source_item_id > ?', $lastId)
                ->order('item.source_item_id')
                ->limit($batchSize)
        );
    }

    /**
     * Lock the chosen source items in the order the recorder locks them
     *
     * @param AdapterInterface $connection
     * @param array $ids
     * @return Select
     */
    private function selectForLock(AdapterInterface $connection, array $ids): Select
    {
        return $connection->select()
            ->from(['item' => $this->getSourceItemTable()], ['source_item_id'])
            ->where('item.source_item_id IN (?)', $ids)
            ->order('item.source_item_id')
            ->forUpdate(true);
    }

    /**
     * Select the chosen source items with their current quantity
     *
     * @param array $ids
     * @return Select
     */
    private function selectItems(array $ids): Select
    {
        return $this->resourceConnection->getConnection()->select()
            ->from(
                ['item' => $this->getSourceItemTable()],
                ['source_item_id', 'source_code', 'sku', 'quantity']
            )
            ->where('item.source_item_id IN (?)', $ids)
            ->order('item.source_item_id');
    }

    /**
     * Keep only source items with a quantity other than zero and no history row
     *
     * @param AdapterInterface $connection
     * @param Select $select
     * @return Select
     */
    private function filterWithoutHistory(AdapterInterface $connection, Select $select): Select
    {
        $history = $connection->select()
            ->from(
                ['history' => $this->resourceConnection->getTableName(AdjustmentWriter::TABLE)],
                ['adjustment_id']
            )
            ->where('history.source_code = item.source_code')
            ->where('history.sku = item.sku');

        return $select
            ->where('item.quantity <> 0')
            ->where('NOT EXISTS (' . $history . ')');
    }

    /**
     * Name of the source item table
     *
     * @return string
     */
    private function getSourceItemTable(): string
    {
        return $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM);
    }

    /**
     * Opening rows whose delta and resulting quantity are the current quantity
     *
     * @param array $items
     * @param string $requestId
     * @return array
     */
    private function buildRows(array $items, string $requestId): array
    {
        $rows = [];
        foreach ($items as $item) {
            $quantity = round((float)$item['quantity'], self::PRECISION);
            $rows[] = [
                'source_code' => $item['source_code'],
                'sku' => $item['sku'],
                'state' => 'available',
                'delta' => $quantity,
                'quantity_after' => $quantity,
                'status_before' => null,
                'status_after' => null,
                'reason' => AdjustmentReason::Other->value,
                'actor_type' => ActorType::System->value,
                'actor_id' => null,
                'actor_label' => null,
                'reference_type' => null,
                'reference_id' => null,
                'request_id' => $requestId,
                'note' => self::NOTE,
            ];
        }

        return $rows;
    }
}
