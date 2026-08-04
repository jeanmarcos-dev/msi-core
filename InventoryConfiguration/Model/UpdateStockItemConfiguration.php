<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;

/**
 * Apply a partial stock item configuration update to a set of skus.
 */
class UpdateStockItemConfiguration
{
    /**
     * @var StockItemConfigurationResource
     */
    private $stockItemConfigurationResource;

    /**
     * @var CacheStorage
     */
    private $cacheStorage;

    /**
     * @param StockItemConfigurationResource $stockItemConfigurationResource
     * @param CacheStorage $cacheStorage
     */
    public function __construct(
        StockItemConfigurationResource $stockItemConfigurationResource,
        CacheStorage $cacheStorage
    ) {
        $this->stockItemConfigurationResource = $stockItemConfigurationResource;
        $this->cacheStorage = $cacheStorage;
    }

    /**
     * Apply a partial stock item configuration update to a set of skus.
     *
     * Keys that are not configuration columns are ignored, so callers may pass a
     * whole legacy stock item payload.
     *
     * @param string[] $skus
     * @param array<string,mixed> $data
     * @return void
     */
    public function execute(array $skus, array $data): void
    {
        $fields = array_intersect_key($data, array_flip(StockItemConfigurationResource::FIELDS));
        if (empty($fields) || empty($skus)) {
            return;
        }

        $this->stockItemConfigurationResource->updateBySkus($skus, $fields);
        foreach ($skus as $sku) {
            $this->cacheStorage->delete((string)$sku);
        }
    }
}
