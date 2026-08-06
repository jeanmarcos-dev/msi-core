<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
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
