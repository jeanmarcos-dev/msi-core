<?php
/**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryExportStock\Model;

use Magento\InventoryConfiguration\Model\GetStockItemConfigurationBySku;
use Magento\InventoryConfiguration\Model\StockItemConfigurationFactory;
use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;

/**
 * @inheritdoc
 */
class GetStockItemConfiguration
{
    /**
     * @var GetStockItemConfigurationBySku
     */
    private $getStockItemConfigurationBySku;

    /**
     * @var StockItemConfigurationFactory
     */
    private $stockItemConfigurationFactory;

    /**
     * @param GetStockItemConfigurationBySku $getStockItemConfigurationBySku
     * @param StockItemConfigurationFactory $stockItemConfigurationFactory
     */
    public function __construct(
        GetStockItemConfigurationBySku $getStockItemConfigurationBySku,
        StockItemConfigurationFactory $stockItemConfigurationFactory
    ) {
        $this->getStockItemConfigurationBySku = $getStockItemConfigurationBySku;
        $this->stockItemConfigurationFactory = $stockItemConfigurationFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute(string $sku): StockItemConfigurationInterface
    {
        return $this->stockItemConfigurationFactory->create(
            [
                'stockItem' => $this->getStockItemConfigurationBySku->execute($sku)
            ]
        );
    }
}
