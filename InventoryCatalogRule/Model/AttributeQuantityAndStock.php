<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogRule\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessor\ConditionProcessor\CustomConditionInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection as CatalogCollection;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\Stock\CollectionFactory;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;

/**
 * Based on Magento\Framework\Api\Filter builds condition
 * that can be applied to Catalog\Model\ResourceModel\Product\Collection
 * to filter products by quantity_and_stock_status
 */
class AttributeQuantityAndStock implements CustomConditionInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var CollectionFactory
     */
    private $stockCollectionFactory;

    /**
     * @var StockIndexTableNameResolverInterface
     */
    private $stockIndexTableNameResolver;

    /**
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $stockCollectionFactory
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        CollectionFactory $stockCollectionFactory,
        StockIndexTableNameResolverInterface $stockIndexTableNameResolver
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->stockCollectionFactory = $stockCollectionFactory;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
    }

    /**
     * Builds condition to filter product collection by stock
     *
     * @param Filter $filter
     * @return string
     */
    public function build(Filter $filter): string
    {
        $collection = $this->stockCollectionFactory->create();
        $quantitySelect = $this->resourceConnection->getConnection()->select()
            ->from(
                ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'cpe.entity_id'
            );
        foreach ($collection->getAllIds() as $stockId) {
            $stockIndexTableName = $this->stockIndexTableNameResolver->execute((int)$stockId);
            $quantitySelect->joinInner(
                ['stock_'.$stockId => $stockIndexTableName],
                'stock_'.$stockId.'.sku = cpe.sku',
                []
            )->orWhere(
                'stock_' . $stockId . '.is_salable =' . $filter->getValue()
            );
        }
        $selectCondition = [
            $this->mapConditionType($filter->getConditionType()) => $quantitySelect
        ];
        return $this->resourceConnection->getConnection()
            ->prepareSqlCondition(CatalogCollection::MAIN_TABLE_ALIAS . '.entity_id', $selectCondition);
    }

    /**
     * Map equal and not equal conditions to in and not in
     *
     * @param string $conditionType
     * @return string
     */
    private function mapConditionType(string $conditionType): string
    {
        $ninConditions = ['neq'];
        return in_array($conditionType, $ninConditions, true) ? 'nin' : 'in';
    }
}
