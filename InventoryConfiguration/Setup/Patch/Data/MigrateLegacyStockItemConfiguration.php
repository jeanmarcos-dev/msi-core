<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration;

/**
 * Seed inventory_stock_item_configuration from the legacy per-product stock item rows.
 */
class MigrateLegacyStockItemConfiguration implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param DefaultStockProviderInterface $defaultStockProvider
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        DefaultStockProviderInterface $defaultStockProvider
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->defaultStockProvider = $defaultStockProvider;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();

        $columns = ['product.sku'];
        foreach (StockItemConfiguration::FIELDS as $field) {
            $columns[] = 'legacy.' . $field;
        }

        $select = $connection->select()
            ->from(['legacy' => $this->moduleDataSetup->getTable('cataloginventory_stock_item')], $columns)
            ->join(
                ['product' => $this->moduleDataSetup->getTable('catalog_product_entity')],
                'product.entity_id = legacy.product_id',
                []
            )
            ->where('legacy.stock_id = ?', $this->defaultStockProvider->getId())
            ->where('legacy.website_id = ?', 0);

        $connection->query(
            $connection->insertFromSelect(
                $select,
                $this->moduleDataSetup->getTable(StockItemConfiguration::TABLE_NAME),
                array_merge([StockItemConfiguration::SKU], StockItemConfiguration::FIELDS),
                AdapterInterface::INSERT_ON_DUPLICATE
            )
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
