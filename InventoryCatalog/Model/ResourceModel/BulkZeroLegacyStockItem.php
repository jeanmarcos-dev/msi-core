<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model\ResourceModel;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryConfiguration\Model\UpdateStockItemConfiguration;

/**
 * Set quantity=0 to legacy cataloginventory_stock_item table for a set of skus via plain MySql query
 */
class BulkZeroLegacyStockItem
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var GetProductIdsBySkusInterface
     */
    private $getProductIdsBySkus;

    /**
     * @var UpdateStockItemConfiguration
     */
    private $updateStockItemConfiguration;

    /**
     * @param ResourceConnection $resourceConnection
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param UpdateStockItemConfiguration $updateStockItemConfiguration
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        GetProductIdsBySkusInterface $getProductIdsBySkus,
        UpdateStockItemConfiguration $updateStockItemConfiguration
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->updateStockItemConfiguration = $updateStockItemConfiguration;
    }

    /**
     * Sets quantity to 0 and marks items as out of stock in the legacy stock table for the given SKUs.
     *
     * @param array $skus
     * @return void
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(array $skus)
    {
        $productIds = array_values($this->getProductIdsBySkus->execute($skus));

        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName('cataloginventory_stock_item'),
            [
                StockItemInterface::QTY => 0,
                StockItemInterface::IS_IN_STOCK => 0,
            ],
            [
                StockItemInterface::PRODUCT_ID . ' IN (?)' => $productIds,
                'website_id = ?' => 0,
            ]
        );
        $this->updateStockItemConfiguration->execute($skus, [StockItemInterface::IS_IN_STOCK => 0]);
    }
}
