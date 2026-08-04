<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;

/**
 * Get the stock item configuration entity of a single sku.
 */
class GetStockItemConfigurationBySku
{
    /**
     * @var StockItemInterfaceFactory
     */
    private $stockItemFactory;

    /**
     * @var GetProductIdsBySkusInterface
     */
    private $getProductIdsBySkus;

    /**
     * @var GetStockItemsConfigurationInterface
     */
    private $getStockItemsConfiguration;

    /**
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param GetStockItemsConfigurationInterface $getStockItemsConfiguration
     */
    public function __construct(
        StockItemInterfaceFactory $stockItemFactory,
        GetProductIdsBySkusInterface $getProductIdsBySkus,
        GetStockItemsConfigurationInterface $getStockItemsConfiguration
    ) {
        $this->stockItemFactory = $stockItemFactory;
        $this->getProductIdsBySkus = $getProductIdsBySkus;
        $this->getStockItemsConfiguration = $getStockItemsConfiguration;
    }

    /**
     * Get the stock item configuration entity of a single sku.
     *
     * @param string $sku
     * @return StockItemInterface
     * @throws LocalizedException
     */
    public function execute(string $sku): StockItemInterface
    {
        try {
            $this->getProductIdsBySkus->execute([$sku]);
        } catch (NoSuchEntityException $skuNotFoundInCatalog) {
            $stockItem = $this->stockItemFactory->create();
            // Make possible to Manage Stock for Products removed from Catalog
            $stockItem->setManageStock(true);
            return $stockItem;
        }

        $stockItems = $this->getStockItemsConfiguration->execute([$sku]);

        return $stockItems[$sku] ?? $this->stockItemFactory->create();
    }
}
