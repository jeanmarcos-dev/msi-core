<?php
/**
 * Copyright 2023 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;

/**
 * @inheritdoc
 */
class GetStockItemsData implements GetStockItemsDataInterface
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @var StockIndexTableNameResolverInterface
     */
    private StockIndexTableNameResolverInterface $stockIndexTableNameResolver;

    /**
     * @param ResourceConnection $resource
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     */
    public function __construct(
        ResourceConnection $resource,
        StockIndexTableNameResolverInterface $stockIndexTableNameResolver
    ) {
        $this->resource = $resource;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, int $stockId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(
                $this->stockIndexTableNameResolver->execute($stockId),
                [
                    GetStockItemsDataInterface::SKU => IndexStructure::SKU,
                    GetStockItemsDataInterface::QUANTITY => IndexStructure::QUANTITY,
                    GetStockItemsDataInterface::IS_SALABLE => IndexStructure::IS_SALABLE,
                ]
            )->where(
                IndexStructure::SKU . ' IN (?)',
                $skus
            );

        try {
            $results = [];
            foreach ($connection->fetchAll($select) ?: [] as $row) {
                $results[$row['sku']] = [
                    GetStockItemsDataInterface::QUANTITY => $row['quantity'],
                    GetStockItemsDataInterface::IS_SALABLE => $row['is_salable'],
                ];
            }
        } catch (\Exception $e) {
            throw new LocalizedException(__('Could not receive Stock Item data'), $e);
        }

        return $this->normalizeResults($skus, $results);
    }

    /**
     * Return results with original SKUs as keys.
     *
     * @param array $originalSkus
     * @param array $results
     * @return array
     */
    private function normalizeResults(array $originalSkus, array $results): array
    {
        $normalizedResults = [];
        foreach ($results as $sku => $result) {
            $normalizedResults[$this->normalizeSku((string) $sku)] = $result;
        }
        
        $finalResults = [];
        foreach (array_unique($originalSkus) as $sku) {
            $normalizedSku = $this->normalizeSku((string) $sku);
            if (isset($results[$sku])) {
                $finalResults[$sku] = $results[$sku];
            } elseif (isset($normalizedResults[$normalizedSku])) {
                $finalResults[$sku] = $normalizedResults[$normalizedSku];
            }
        }
        return $finalResults;
    }

    /**
     * Normalize SKU by converting it to lowercase.
     *
     * @param string $sku
     * @return string
     */
    private function normalizeSku(string $sku): string
    {
        return mb_convert_case($sku, MB_CASE_LOWER, 'UTF-8');
    }
}
