<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\Inventory\Model\AreProductsAssignedToStockCache;
use Magento\InventoryApi\Model\CacheInterface;

/**
 * This class is a wrapper for AreProductsAssignedToStockCache.
 *
 * @see \Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface::execute
 */
class AreProductsAssignedToStockCacheProxy implements CacheInterface
{
    /**
     * @param AreProductsAssignedToStockCache $areProductsAssignedToStockCache
     */
    public function __construct(
        private readonly AreProductsAssignedToStockCache $areProductsAssignedToStockCache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function warmup(array $skus, int $stockId): void
    {
        $this->areProductsAssignedToStockCache->warmup($skus, $stockId);
    }

    /**
     * @inheritDoc
     */
    public function clean(array $skus, ?int $stockId): void
    {
        $this->areProductsAssignedToStockCache->clean($skus, $stockId);
    }
}
