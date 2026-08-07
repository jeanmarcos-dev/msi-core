<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Zend_Db_Expr;

/**
 * Sum the quantity a stock holds for each sku, whatever the status of its source items.
 *
 * The MSI index nulls the quantity of an out of stock source item so that it does not count
 * towards salability. The legacy stock item qty is not a salability figure though: consumers
 * read it to decide the status itself, so it has to carry the quantity that is really there.
 */
class GetStockQuantityBySkuList
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Read the quantity every sku holds on a stock.
     *
     * @param string[] $skus
     * @param int $stockId
     * @return float[] keyed by sku
     */
    public function execute(array $skus, int $stockId): array
    {
        if (!$skus) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()->from(
            ['stock_link' => $this->resourceConnection->getTableName('inventory_source_stock_link')],
            []
        )->joinInner(
            ['source' => $this->resourceConnection->getTableName('inventory_source')],
            'stock_link.source_code = source.source_code',
            []
        )->joinInner(
            ['source_item' => $this->resourceConnection->getTableName('inventory_source_item')],
            'stock_link.source_code = source_item.source_code',
            []
        )->where(
            'stock_link.stock_id = ?',
            $stockId
        )->where(
            'source.enabled = ?',
            1
        )->where(
            'source_item.sku IN (?)',
            $skus
        )->group(
            ['source_item.sku']
        )->columns(
            [
                'sku' => 'source_item.sku',
                'quantity' => new Zend_Db_Expr(sprintf('SUM(source_item.%s)', SourceItemInterface::QUANTITY)),
            ]
        );

        $quantityBySku = [];
        foreach ($connection->fetchAll($select) as $row) {
            $quantityBySku[$row['sku']] = (float)$row['quantity'];
        }

        return $quantityBySku;
    }
}
