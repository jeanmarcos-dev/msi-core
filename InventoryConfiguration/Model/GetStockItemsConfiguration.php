<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;

/**
 * @inheritdoc
 */
class GetStockItemsConfiguration implements GetStockItemsConfigurationInterface
{
    /**
     * @var StockItemInterfaceFactory
     */
    private $stockItemFactory;

    /**
     * @var StockItemConfigurationResource
     */
    private $stockItemConfigurationResource;

    /**
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param StockItemConfigurationResource $stockItemConfigurationResource
     */
    public function __construct(
        StockItemInterfaceFactory $stockItemFactory,
        StockItemConfigurationResource $stockItemConfigurationResource
    ) {
        $this->stockItemFactory = $stockItemFactory;
        $this->stockItemConfigurationResource = $stockItemConfigurationResource;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus): array
    {
        $stockItems = [];
        foreach ($this->stockItemConfigurationResource->get($skus) as $sku => $row) {
            $stockItems[$sku] = $this->stockItemFactory->create(['data' => $row]);
        }

        return $stockItems;
    }
}
