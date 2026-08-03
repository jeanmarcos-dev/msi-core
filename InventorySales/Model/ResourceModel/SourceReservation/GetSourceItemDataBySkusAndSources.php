<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * Load the quantity and stock status of the given SKUs on the given sources.
 *
 * Unlike GetSourceItemQuantityBySkusAndSources this keeps out-of-stock source items, so a caller
 * can tell an empty source apart from one holding quantity that is not offered for sale.
 */
class GetSourceItemDataBySkusAndSources
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get source item quantity and status indexed by source code and SKU.
     *
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return array<string, array<string, array<string, float|int>>> [source_code][sku] => [quantity, status]
     */
    public function execute(array $skus, array $sourceCodes): array
    {
        if (empty($skus) || empty($sourceCodes)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM),
                [
                    SourceItemInterface::SOURCE_CODE,
                    SourceItemInterface::SKU,
                    SourceItemInterface::QUANTITY,
                    SourceItemInterface::STATUS,
                ]
            )
            ->where(SourceItemInterface::SOURCE_CODE . ' IN (?)', $sourceCodes)
            ->where(SourceItemInterface::SKU . ' IN (?)', $skus);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[$row[SourceItemInterface::SOURCE_CODE]][$row[SourceItemInterface::SKU]] = [
                'quantity' => (float) $row[SourceItemInterface::QUANTITY],
                'status' => (int) $row[SourceItemInterface::STATUS],
            ];
        }

        return $result;
    }
}
