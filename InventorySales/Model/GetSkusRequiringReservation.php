<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model;

use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;

/**
 * Keep the SKUs whose sale writes a reservation on the stock.
 */
class GetSkusRequiringReservation
{
    /**
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param StockConfigurationInterface $stockConfiguration
     */
    public function __construct(
        private readonly GetStockItemConfigurationInterface $getStockItemConfiguration,
        private readonly StockConfigurationInterface $stockConfiguration
    ) {
    }

    /**
     * Filter the SKUs down to those with managed stock, when stock is subtracted at all.
     *
     * @param array $skus
     * @param int $stockId
     * @return string[]
     */
    public function execute(array $skus, int $stockId): array
    {
        if (!$this->stockConfiguration->canSubtractQty()) {
            return [];
        }

        $reserved = [];
        foreach ($skus as $sku) {
            $sku = (string)$sku;
            if ($this->getStockItemConfiguration->execute($sku, $stockId)->isManageStock()) {
                $reserved[] = $sku;
            }
        }

        return $reserved;
    }
}
