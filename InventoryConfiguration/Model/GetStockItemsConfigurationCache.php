<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\InventoryApi\Model\CacheInterface;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;

/**
 * @inheritdoc
 */
class GetStockItemsConfigurationCache implements GetStockItemsConfigurationInterface, CacheInterface
{
    /**
     * @param GetStockItemsConfiguration $getStockItemsConfiguration
     * @param CacheStorage $cacheStorage
     */
    public function __construct(
        private readonly GetStockItemsConfiguration $getStockItemsConfiguration,
        private readonly CacheStorage $cacheStorage
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus): array
    {
        $stockItems = [];
        $skusToLoad = [];
        foreach ($skus as $sku) {
            $stockItem = $this->cacheStorage->get((string)$sku);
            if ($stockItem) {
                $stockItems[(string)$sku] = $stockItem;
            } else {
                $skusToLoad[] = $sku;
            }
        }

        if (!empty($skusToLoad)) {
            foreach ($this->getStockItemsConfiguration->execute($skusToLoad) as $sku => $stockItem) {
                $this->cacheStorage->set((string)$sku, $stockItem);
                $stockItems[(string)$sku] = $stockItem;
            }
        }

        return $stockItems;
    }

    /**
     * @inheritdoc
     */
    public function warmup(array $skus, int $stockId): void
    {
        $this->execute($skus);
    }

    /**
     * @inheritdoc
     */
    public function clean(array $skus, ?int $stockId): void
    {
        foreach ($skus as $sku) {
            $this->cacheStorage->delete((string)$sku);
        }
    }
}
