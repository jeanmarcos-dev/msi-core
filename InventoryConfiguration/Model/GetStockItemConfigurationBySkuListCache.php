<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\InventoryConfigurationApi\Model\GetStockItemConfigurationBySkuListCacheInterface;

class GetStockItemConfigurationBySkuListCache implements GetStockItemConfigurationBySkuListCacheInterface
{
    /**
     * @param GetStockItemsConfigurationCache $getStockItemsConfigurationCache
     * @param GetLegacyStockItemsCache $getLegacyStockItemsCache
     */
    public function __construct(
        private readonly GetStockItemsConfigurationCache $getStockItemsConfigurationCache,
        private readonly GetLegacyStockItemsCache $getLegacyStockItemsCache,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function warmup(array $skus, int $stockId): void
    {
        $this->getStockItemsConfigurationCache->warmup($skus, $stockId);
        $this->getLegacyStockItemsCache->warmup($skus, $stockId);
    }

    /**
     * @inheritdoc
     */
    public function clean(array $skus, ?int $stockId): void
    {
        $this->getStockItemsConfigurationCache->clean($skus, $stockId);
        $this->getLegacyStockItemsCache->clean($skus, $stockId);
    }
}
