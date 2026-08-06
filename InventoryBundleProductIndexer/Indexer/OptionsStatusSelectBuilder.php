<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProductIndexer\Indexer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryConfigurationApi\Model\InventoryConfigurationInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\Stock\ReservationsIndexTable;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexAlias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameResolverInterface;
use Magento\Store\Model\Store;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OptionsStatusSelectBuilder
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param IndexNameBuilder $indexNameBuilder
     * @param IndexNameResolverInterface $indexNameResolver
     * @param MetadataPool $metadataPool
     * @param InventoryConfigurationInterface $inventoryConfiguration
     * @param ReservationsIndexTable $reservationsIndexTable
     * @param Config $eavConfig
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly IndexNameBuilder $indexNameBuilder,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly MetadataPool $metadataPool,
        private readonly InventoryConfigurationInterface $inventoryConfiguration,
        private readonly ReservationsIndexTable $reservationsIndexTable,
        private readonly Config $eavConfig,
    ) {
    }

    /**
     * Build bundle options stock status select
     *
     * @param int $stockId
     * @param string[] $skuList
     * @param IndexAlias $indexAlias
     * @return Select
     */
    public function execute(int $stockId, array $skuList = [], IndexAlias $indexAlias = IndexAlias::MAIN): Select
    {
        $indexName = $this->indexNameBuilder->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string) $stockId)
            ->setAlias($indexAlias->value)
            ->build();
        $indexTableName = $this->indexNameResolver->resolveName($indexName);
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $productLinkField = $metadata->getLinkField();
        $reservationsTableName = $this->reservationsIndexTable->getTableName($stockId);

        // Every option of the bundle has to reach the result, including one whose selections are all
        // missing from the index. Starting from the index instead would drop the option entirely, and an
        // option that is simply absent reads as one that raises no objection to selling the bundle.
        $select = $this->resourceConnection->getConnection()->select()
            ->from(
                ['bundle_option' => $this->resourceConnection->getTableName('catalog_product_bundle_option')],
                []
            )->joinInner(
                ['parent_product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'parent_product_entity.' . $productLinkField . ' = bundle_option.parent_id',
                []
            )->joinLeft(
                ['bundle_selection' => $this->resourceConnection->getTableName('catalog_product_bundle_selection')],
                'bundle_selection.option_id = bundle_option.option_id',
                []
            )->joinLeft(
                ['product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'product_entity.entity_id = bundle_selection.product_id',
                []
            )->joinLeft(
                ['stock' => $indexTableName],
                'stock.sku = product_entity.sku',
                []
            )->joinLeft(
                ['product_status' => $this->resourceConnection->getTableName('catalog_product_entity_int')],
                'product_entity.' . $productLinkField . ' = product_status.' . $productLinkField
                . ' AND product_status.attribute_id = ' . $this->getStatusAttributeId()
                . ' AND product_status.store_id = ' . Store::DEFAULT_STORE_ID,
                []
            )->joinLeft(
                ['stock_item_configuration' => $this->resourceConnection->getTableName(
                    'inventory_stock_item_configuration'
                )],
                'stock_item_configuration.sku = product_entity.sku',
                []
            )->joinLeft(
                ['reservations' => $this->resourceConnection->getTableName($reservationsTableName)],
                'reservations.sku = stock.sku',
                []
            )->group(
                ['bundle_option.parent_id', 'bundle_option.option_id']
            )->columns(
                [
                    'sku' => 'parent_product_entity.sku',
                    'option_id' => 'bundle_option.option_id',
                    'required' => 'bundle_option.required',
                    'quantity' => new \Zend_Db_Expr('SUM(IFNULL(stock.quantity, 0))'),
                    'stock_status' => $this->getOptionsStatusExpression(),
                ]
            );

        if (!empty($skuList)) {
            $select->where('parent_product_entity.sku IN (?)', $skuList);
        }

        return $select;
    }

    /**
     * Build expression for bundle options stock status
     *
     * @return \Zend_Db_Expr
     */
    private function getOptionsStatusExpression(): \Zend_Db_Expr
    {
        $connection = $this->resourceConnection->getConnection();

        $reservationQty = $connection->getIfNullSql('reservations.reservation_qty');
        $quantity = '(stock.quantity - stock_item_configuration.min_qty + ' . $reservationQty . ')';
        $isAvailableExpr = $connection->getCheckSql(
            'bundle_selection.selection_can_change_qty = 0 AND bundle_selection.selection_qty > ' . $quantity,
            '0',
            'stock.is_salable'
        );

        if ($this->inventoryConfiguration->getBackorders()) {
            $backordersExpr = $connection->getCheckSql(
                'stock_item_configuration.use_config_backorders = 0 AND stock_item_configuration.backorders = 0',
                $isAvailableExpr,
                'stock.is_salable'
            );
        } else {
            $backordersExpr = $connection->getCheckSql(
                'stock_item_configuration.use_config_backorders = 0 AND stock_item_configuration.backorders > 0',
                'stock.is_salable',
                $isAvailableExpr
            );
        }

        if ($this->inventoryConfiguration->getManageStock()) {
            $statusExpr = $connection->getCheckSql(
                'stock_item_configuration.use_config_manage_stock = 0 AND stock_item_configuration.manage_stock = 0',
                1,
                $backordersExpr
            );
        } else {
            $statusExpr = $connection->getCheckSql(
                'stock_item_configuration.use_config_manage_stock = 0 AND stock_item_configuration.manage_stock = 1',
                $backordersExpr,
                1
            );
        }

        // A selection only counts while its product is enabled and carries an index row. Without this the
        // option would inherit the salability of a disabled product, or of one no stock holds at all.
        $sellableSelection = $connection->getCheckSql(
            'stock.sku IS NOT NULL AND product_status.value = ' . ProductStatus::STATUS_ENABLED,
            $statusExpr,
            '0'
        );

        return new \Zend_Db_Expr('MAX(' . $sellableSelection . ')');
    }

    /**
     * Retrieve the id of the product status attribute
     *
     * @return int
     */
    private function getStatusAttributeId(): int
    {
        return (int) $this->eavConfig->getAttribute(Product::ENTITY, ProductInterface::STATUS)->getId();
    }
}
