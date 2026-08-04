<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Exception\LocalizedException;

interface GetStockItemsConfigurationInterface
{
    /**
     * Get stock item configuration entities by skus, indexed by sku.
     *
     * @param string[] $skus
     * @return StockItemInterface[]
     * @throws LocalizedException
     */
    public function execute(array $skus): array;
}
