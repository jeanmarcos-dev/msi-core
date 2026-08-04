<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProductIndexer\Indexer;

use Magento\Bundle\Model\Product\Type as BundleProductType;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\InventoryConfigurationApi\Model\InventoryConfigurationInterface;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\InventoryIndexer\Indexer\SiblingSelectBuilderInterface;
use Magento\InventoryMultiDimensionalIndexerApi\Model\IndexAlias;

/**
 * Get bundle product for given stock select builder.
 */
class SelectBuilder implements SiblingSelectBuilderInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var OptionsStatusSelectBuilder
     */
    private $optionsStatusSelectBuilder;

    /**
     * @param ResourceConnection $resourceConnection
     * @param OptionsStatusSelectBuilder $optionsStatusSelectBuilder
     * @param InventoryConfigurationInterface $configuration
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        OptionsStatusSelectBuilder $optionsStatusSelectBuilder,
        private readonly InventoryConfigurationInterface $configuration
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->optionsStatusSelectBuilder = $optionsStatusSelectBuilder;
    }

    /**
     * @inheritdoc
     */
    public function getSelect(int $stockId, array $skuList = [], IndexAlias $indexAlias = IndexAlias::MAIN): Select
    {
        $connection = $this->resourceConnection->getConnection();

        $optionsStatusSelect = $this->optionsStatusSelectBuilder->execute($stockId, $skuList, $indexAlias);
        $isRequiredOptionUnavailable = $connection->getCheckSql(
            'options.required AND options.stock_status = 0',
            '1',
            '0'
        );

        $manageStock = '(stock_item_configuration.use_config_manage_stock = 0'
            . ' AND stock_item_configuration.manage_stock = 1)';
        if (((int)$this->configuration->getManageStock()) === 1) {
            $manageStock .= ' OR stock_item_configuration.use_config_manage_stock = 1';
            $manageStock = "($manageStock)";
        }

        $select = $connection->select()
            ->from(
                ['product_entity' => $this->resourceConnection->getTableName('catalog_product_entity')],
                []
            )->joinLeft(
                ['options' => $optionsStatusSelect],
                'options.sku = product_entity.sku',
                []
            )->joinLeft(
                ['stock_item_configuration' => $this->resourceConnection->getTableName(
                    'inventory_stock_item_configuration'
                )],
                'stock_item_configuration.sku = product_entity.sku',
                []
            )->where(
                'product_entity.type_id = ?',
                BundleProductType::TYPE_CODE
            )->group(
                ['product_entity.sku']
            )->columns([
                IndexStructure::SKU => 'product_entity.sku',
                IndexStructure::QUANTITY => $connection->getIfNullSql('SUM(options.quantity)', '0'),
                IndexStructure::IS_SALABLE => $connection->getCheckSql(
                    "(stock_item_configuration.is_in_stock = 0 AND $manageStock) OR options.sku IS NULL",
                    '0',
                    'MAX(' . $isRequiredOptionUnavailable . ') = 0 AND MAX(options.stock_status) = 1'
                ),
            ])
            ->order('product_entity.sku ASC');

        if (!empty($skuList)) {
            $select->where('product_entity.sku IN (?)', $skuList);
        }

        return $select;
    }
}
