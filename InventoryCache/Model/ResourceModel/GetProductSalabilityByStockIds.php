<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCache\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;

class GetProductSalabilityByStockIds
{
    /**
     * @param ResourceConnection $resource
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     * @param string $productTableName
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StockIndexTableNameResolverInterface $stockIndexTableNameResolver,
        private readonly string $productTableName
    ) {
    }

    /**
     * Read the indexed salability of every product of each stock.
     *
     * @param int[] $stockIds
     * @return array<int, array<int, int>>
     */
    public function execute(array $stockIds): array
    {
        $connection = $this->resource->getConnection();
        $salability = [];
        foreach ($stockIds as $stockId) {
            $select = $connection->select()
                ->from(
                    ['stock_index' => $this->stockIndexTableNameResolver->execute((int)$stockId)],
                    []
                )
                ->join(
                    ['product' => $this->resource->getTableName($this->productTableName)],
                    'product.sku = stock_index.' . IndexStructure::SKU,
                    ['product.entity_id', 'stock_index.' . IndexStructure::IS_SALABLE]
                );
            $salability[(int)$stockId] = array_map('intval', $connection->fetchPairs($select));
        }

        return $salability;
    }
}
