<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\CatalogInventory\Api\StockItemRepository;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;

/**
 * Serve a stock item read by id from MSI.
 *
 * cataloginventory_stock_item is frozen, so loading a stock item out of it finds nothing. The registry
 * provider keys the items it builds by product id, which is what this repository is asked for.
 */
class AdaptGetStockItemPlugin
{
    /**
     * @param StockRegistryProviderInterface $stockRegistryProvider
     * @param StockConfigurationInterface $stockConfiguration
     */
    public function __construct(
        private readonly StockRegistryProviderInterface $stockRegistryProvider,
        private readonly StockConfigurationInterface $stockConfiguration
    ) {
    }

    /**
     * Answer from MSI, falling back to the original so its own not found error is the one raised.
     *
     * @param StockItemRepositoryInterface $subject
     * @param callable $proceed
     * @param int $stockItemId
     * @return StockItemInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundGet(
        StockItemRepositoryInterface $subject,
        callable $proceed,
        $stockItemId
    ): StockItemInterface {
        $stockItem = $this->stockRegistryProvider->getStockItem(
            (int) $stockItemId,
            $this->stockConfiguration->getDefaultScopeId()
        );

        return $stockItem->getItemId() ? $stockItem : $proceed($stockItemId);
    }
}
