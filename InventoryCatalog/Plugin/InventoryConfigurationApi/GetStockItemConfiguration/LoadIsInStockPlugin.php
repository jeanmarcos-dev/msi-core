<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\InventoryConfigurationApi\GetStockItemConfiguration;

use Magento\InventoryConfiguration\Model\GetStockItemConfigurationBySku;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;

/**
 * Load the stored IsInStock flag for StockItemConfiguration
 */
class LoadIsInStockPlugin
{
    /**
     * @var GetStockItemConfigurationBySku
     */
    private $getStockItemConfigurationBySku;

    /**
     * @param GetStockItemConfigurationBySku $getStockItemConfigurationBySku
     */
    public function __construct(GetStockItemConfigurationBySku $getStockItemConfigurationBySku)
    {
        $this->getStockItemConfigurationBySku = $getStockItemConfigurationBySku;
    }

    /**
     * Updates the stock item's "is in stock" status and sets it in the extension attributes.
     *
     * @param GetStockItemConfigurationInterface $subject
     * @param StockItemConfigurationInterface $result
     * @param string $sku
     * @param int $stockId
     * @return StockItemConfigurationInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute(
        GetStockItemConfigurationInterface $subject,
        StockItemConfigurationInterface $result,
        string $sku,
        int $stockId
    ): StockItemConfigurationInterface {
        $stockItem = $this->getStockItemConfigurationBySku->execute($sku);
        $extensionAttributes = $result->getExtensionAttributes();
        $extensionAttributes->setIsInStock((bool)(int)$stockItem->getIsInStock());
        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}
