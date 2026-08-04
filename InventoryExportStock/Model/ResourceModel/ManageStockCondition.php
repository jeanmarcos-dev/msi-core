<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryExportStock\Model\ResourceModel;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\Framework\DB\Select;

/**
 * Provides conditions for managing stock configuration in database queries,
 * based on global and product-specific settings.
 */
class ManageStockCondition
{
    /**
     * @var StockConfigurationInterface
     */
    private $configuration;

    /**
     * @param StockConfigurationInterface $configuration
     */
    public function __construct(StockConfigurationInterface $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Provide product manage stock condition for db select
     *
     * @param Select $select
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Select $select): string
    {
        $globalManageStock = (int)$this->configuration->getManageStock();

        $condition = '
        (stock_item_configuration.use_config_manage_stock = 0 AND stock_item_configuration.manage_stock = 1)';
        if (1 === $globalManageStock) {
            $condition .= ' OR stock_item_configuration.use_config_manage_stock = 1';
        }

        return $condition;
    }
}
