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
        if (empty($skus)) {
            return [];
        }

        try {
            $results = [];
            foreach ($this->getStockItemRows($skus, $stockId) as $row) {
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
     * Return stock item information
     *
     * @param array $skus
     * @param int $stockId
     * @return array
     * @throws \Exception
     */
    private function getStockItemRows(array $skus, int $stockId): array
    {
        $connection = $this->resource->getConnection();

        $values = array_values($skus);
        $keys = array_map(static fn (int $i): string => 'sku' . $i, array_keys($values));
        $placeholders = array_map(static fn (string $key): string => ':' . $key, $keys);
        $bind = array_combine($keys, $values);

        $select = $connection->select()
            ->from(
                $this->stockIndexTableNameResolver->execute($stockId),
                [
                    GetStockItemsDataInterface::SKU => IndexStructure::SKU,
                    GetStockItemsDataInterface::QUANTITY => IndexStructure::QUANTITY,
                    GetStockItemsDataInterface::IS_SALABLE => IndexStructure::IS_SALABLE,
                ]
            )->where(
                IndexStructure::SKU . ' IN (' . implode(',', $placeholders) . ')'
            );

        return $connection->fetchAll($select, $bind) ?: [];
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
