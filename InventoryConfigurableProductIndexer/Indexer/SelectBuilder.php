<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProductIndexer\Indexer;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\InventoryConfigurationApi\Model\InventoryConfigurationInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryIndexer\Indexer\SiblingSelectBuilderInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexAlias;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameBuilder;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexNameResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Store\Model\Store;

/**
 * Get configurable product for given stock select builder
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SelectBuilder implements SiblingSelectBuilderInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param IndexNameBuilder $indexNameBuilder
     * @param IndexNameResolverInterface $indexNameResolver
     * @param MetadataPool $metadataPool
     * @param Config $eavConfig
     * @param InventoryConfigurationInterface $configuration
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly IndexNameBuilder $indexNameBuilder,
        private readonly IndexNameResolverInterface $indexNameResolver,
        private readonly MetadataPool $metadataPool,
        private readonly Config $eavConfig,
        private readonly InventoryConfigurationInterface $configuration
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSelect(int $stockId, array $skuList = [], IndexAlias $indexAlias = IndexAlias::MAIN): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $indexName = $this->indexNameBuilder->setIndexId(InventoryIndexer::INDEXER_ID)
            ->addDimension('stock_', (string) $stockId)
            ->setAlias($indexAlias->value)
            ->build();
        $indexTableName = $this->indexNameResolver->resolveName($indexName);
        $metadata = $this->metadataPool->getMetadata(ProductInterface::class);
        $linkField = $metadata->getLinkField();
        $statusAttributeId = $this->getAttribute(ProductInterface::STATUS)->getId();

        $manageStock = '(stock_item_configuration.use_config_manage_stock = 0'
            . ' AND stock_item_configuration.manage_stock = 1)';
        if (((int)$this->configuration->getManageStock()) === 1) {
            $manageStock .= ' OR stock_item_configuration.use_config_manage_stock = 1';
            $manageStock = "($manageStock)";
        }

        $stockHasWebsites = $this->stockHasWebsites($stockId);
        $enabledChildIsSalable = $stockHasWebsites
            ? sprintf(
                'MAX(IF(EXISTS (%s), stock.is_salable, 0))',
                $this->childIsEnabledInAWebsiteOfTheStock($stockId, (int)$statusAttributeId, $linkField)
            )
            : sprintf('MAX(IF(product_status.value = %d, stock.is_salable, 0))', ProductStatus::STATUS_ENABLED);

        $select = $connection->select()
            ->from(
                ['stock' => $indexTableName],
                [
                    IndexStructure::SKU => 'parent_product_entity.sku',
                    IndexStructure::QUANTITY => 'SUM(stock.quantity)',
                    IndexStructure::IS_SALABLE =>
                        "IF(stock_item_configuration.is_in_stock = 0 AND $manageStock, 0, $enabledChildIsSalable)",
                ]
            )->joinInner(
                ['product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'product_entity.sku = stock.sku',
                []
            )->joinInner(
                ['parent_link' => $this->resourceConnection->getTableName('catalog_product_super_link')],
                'parent_link.product_id = product_entity.entity_id',
                []
            )->joinInner(
                ['parent_product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'parent_product_entity.' . $linkField . ' = parent_link.parent_id',
                []
            )->joinLeft(
                ['stock_item_configuration' => $this->resourceConnection->getTableName(
                    'inventory_stock_item_configuration'
                )],
                'stock_item_configuration.sku = parent_product_entity.sku',
                []
            )
            ->group(['parent_product_entity.sku'])
            ->order('parent_product_entity.sku ASC');

        if (!$stockHasWebsites) {
            $select->joinLeft(
                ['product_status' => $this->resourceConnection->getTableName('catalog_product_entity_int')],
                "product_entity.$linkField = product_status.$linkField"
                . " AND product_status.attribute_id = $statusAttributeId"
                . ' AND product_status.store_id = ' . Store::DEFAULT_STORE_ID,
                []
            );
        }

        if ($skuList) {
            $select->where('parent_product_entity.sku IN (?)', $skuList);
        }

        return $select;
    }

    /**
     * Check whether any website is assigned to the stock as a sales channel.
     *
     * @param int $stockId
     * @return bool
     */
    private function stockHasWebsites(int $stockId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName('inventory_stock_sales_channel'), 'COUNT(*)')
            ->where('stock_id = ?', $stockId)
            ->where('type = ?', SalesChannelInterface::TYPE_WEBSITE);

        return (int)$connection->fetchOne($select) > 0;
    }

    /**
     * Build the condition that tells whether the child is enabled in a website of the stock.
     *
     * The website value is the one of its default store view, which Magento writes to every store view of the
     * website, and falls back to the default scope.
     *
     * @param int $stockId
     * @param int $statusAttributeId
     * @param string $linkField
     * @return string
     */
    private function childIsEnabledInAWebsiteOfTheStock(int $stockId, int $statusAttributeId, string $linkField): string
    {
        $resource = $this->resourceConnection;

        return sprintf(
            'SELECT 1 FROM %1$s sw'
            . ' INNER JOIN %2$s sc ON sc.code = sw.code AND sc.type = \'' . SalesChannelInterface::TYPE_WEBSITE
            . '\' AND sc.stock_id = %3$d'
            . ' INNER JOIN %4$s sg ON sg.group_id = sw.default_group_id'
            . ' LEFT JOIN %5$s sv ON sv.%6$s = product_entity.%6$s AND sv.attribute_id = %7$d'
            . ' AND sv.store_id = sg.default_store_id'
            . ' LEFT JOIN %5$s sd ON sd.%6$s = product_entity.%6$s AND sd.attribute_id = %7$d'
            . ' AND sd.store_id = %8$d'
            . ' WHERE COALESCE(sv.value, sd.value) = %9$d',
            $resource->getTableName('store_website'),
            $resource->getTableName('inventory_stock_sales_channel'),
            $stockId,
            $resource->getTableName('store_group'),
            $resource->getTableName('catalog_product_entity_int'),
            $linkField,
            $statusAttributeId,
            Store::DEFAULT_STORE_ID,
            ProductStatus::STATUS_ENABLED
        );
    }

    /**
     * Retrieve catalog_product attribute instance by attribute code
     *
     * @param string $attributeCode
     * @return Attribute
     */
    private function getAttribute($attributeCode): Attribute
    {
        return $this->eavConfig->getAttribute(Product::ENTITY, $attributeCode);
    }
}
