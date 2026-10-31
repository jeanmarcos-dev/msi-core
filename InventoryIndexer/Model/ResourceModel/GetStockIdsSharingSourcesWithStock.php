<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\SourceItem as SourceItemResourceModel;
use Magento\Inventory\Model\ResourceModel\StockSourceLink as StockSourceLinkResourceModel;
use Magento\Inventory\Model\StockSourceLink;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

class GetStockIdsSharingSourcesWithStock
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get the ids of the stocks linked to a source of the given stock that holds one of the skus.
     *
     * @param int $stockId
     * @param string[] $skus
     * @return int[]
     */
    public function execute(int $stockId, array $skus): array
    {
        if (!$skus) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $linkTable = $this->resourceConnection->getTableName(
            StockSourceLinkResourceModel::TABLE_NAME_STOCK_SOURCE_LINK
        );
        $sourceItemTable = $this->resourceConnection->getTableName(SourceItemResourceModel::TABLE_NAME_SOURCE_ITEM);

        $select = $connection->select()
            ->distinct()
            ->from(['own_link' => $linkTable], [])
            ->joinInner(
                ['source_item' => $sourceItemTable],
                'source_item.' . SourceItemInterface::SOURCE_CODE . ' = own_link.' . StockSourceLink::SOURCE_CODE,
                []
            )
            ->joinInner(
                ['sibling_link' => $linkTable],
                'sibling_link.' . StockSourceLink::SOURCE_CODE . ' = own_link.' . StockSourceLink::SOURCE_CODE,
                [StockSourceLink::STOCK_ID]
            )
            ->where('own_link.' . StockSourceLink::STOCK_ID . ' = ?', $stockId)
            ->where('source_item.' . SourceItemInterface::SKU . ' IN (?)', $skus)
            ->order('sibling_link.' . StockSourceLink::STOCK_ID . ' ASC');

        return array_map('intval', $connection->fetchCol($select));
    }
}
