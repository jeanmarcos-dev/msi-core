<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\SaveStockItemConfigurationInterface;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;

/**
 * @inheritdoc
 */
class SaveStockItemConfiguration implements SaveStockItemConfigurationInterface
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
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @var StockItemConfigurationResource
     */
    private $stockItemConfigurationResource;

    /**
     * @var CacheStorage
     */
    private $cacheStorage;

    /**
     * @param ResourceConnection $resourceConnection
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param StockItemConfigurationResource $stockItemConfigurationResource
     * @param CacheStorage $cacheStorage
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        GetProductIdsBySkusInterface $getProductIdsBySkus,
        DefaultStockProviderInterface $defaultStockProvider,
        StockItemConfigurationResource $stockItemConfigurationResource,
        CacheStorage $cacheStorage
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->stockItemConfigurationResource = $stockItemConfigurationResource;
        $this->cacheStorage = $cacheStorage;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(string $sku, int $stockId, StockItemConfigurationInterface $stockItemConfiguration): void
    {
        $productId = $this->getProductIdsBySkus->execute([$sku])[$sku];

        // TODO We ignore $stockId and use $legacyStockId until we have proper multi-stock item configuration
        $legacyStockId = $this->defaultStockProvider->getId();

        $binds = $this->getBinds($stockItemConfiguration);

        $this->stockItemConfigurationResource->save(
            [array_merge([StockItemConfigurationResource::SKU => $sku], $binds)]
        );
        $this->cacheStorage->delete($sku);

        // The legacy table stays in sync until the legacy contracts are served from MSI.
        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName('cataloginventory_stock_item'),
            $binds,
            [
                StockItemInterface::PRODUCT_ID . ' = ?' => $productId,
                StockItemInterface::STOCK_ID . ' = ?' => $legacyStockId,
                'website_id = ?' => 0,
            ]
        );
    }

    /**
     * Maps stock item configuration properties to their corresponding database fields for update operations.
     *
     * @param StockItemConfigurationInterface $stockItemConfiguration
     *
     * @return array
     */
    private function getBinds(StockItemConfigurationInterface $stockItemConfiguration): array
    {
        return [
            StockItemInterface::IS_QTY_DECIMAL => $stockItemConfiguration->isQtyDecimal(),
            StockItemInterface::USE_CONFIG_MIN_QTY => $stockItemConfiguration->isUseConfigMinQty(),
            StockItemInterface::MIN_QTY => $stockItemConfiguration->getMinQty(),
            StockItemInterface::USE_CONFIG_MIN_SALE_QTY => $stockItemConfiguration->isUseConfigMinSaleQty(),
            StockItemInterface::MIN_SALE_QTY => $stockItemConfiguration->getMinSaleQty(),
            StockItemInterface::USE_CONFIG_MAX_SALE_QTY => $stockItemConfiguration->isUseConfigMaxSaleQty(),
            StockItemInterface::MAX_SALE_QTY => $stockItemConfiguration->getMaxSaleQty(),
            StockItemInterface::USE_CONFIG_BACKORDERS => $stockItemConfiguration->isUseConfigBackorders(),
            StockItemInterface::BACKORDERS => $stockItemConfiguration->getBackorders(),
            StockItemInterface::USE_CONFIG_NOTIFY_STOCK_QTY => $stockItemConfiguration->isUseConfigNotifyStockQty(),
            StockItemInterface::NOTIFY_STOCK_QTY => $stockItemConfiguration->getNotifyStockQty(),
            StockItemInterface::USE_CONFIG_QTY_INCREMENTS => $stockItemConfiguration->isUseConfigQtyIncrements(),
            StockItemInterface::QTY_INCREMENTS => $stockItemConfiguration->getQtyIncrements(),
            StockItemInterface::USE_CONFIG_ENABLE_QTY_INC => $stockItemConfiguration->isUseConfigEnableQtyInc(),
            StockItemInterface::ENABLE_QTY_INCREMENTS => $stockItemConfiguration->isEnableQtyIncrements(),
            StockItemInterface::USE_CONFIG_MANAGE_STOCK => $stockItemConfiguration->isUseConfigManageStock(),
            StockItemInterface::MANAGE_STOCK => $stockItemConfiguration->isManageStock(),
            StockItemInterface::LOW_STOCK_DATE => $stockItemConfiguration->getLowStockDate(),
            StockItemInterface::IS_DECIMAL_DIVIDED => $stockItemConfiguration->isDecimalDivided(),
            StockItemInterface::STOCK_STATUS_CHANGED_AUTO => $stockItemConfiguration->getStockStatusChangedAuto(),
            StockItemInterface::IS_IN_STOCK => $stockItemConfiguration->getExtensionAttributes()->getIsInStock()
        ];
    }
}
