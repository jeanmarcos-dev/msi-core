<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\CatalogInventory\Api\StockItemRepository;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\Data\StockItemCollectionInterface;
use Magento\CatalogInventory\Api\StockItemCriteriaInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\InventoryCatalog\Model\StockRegistryProvider;

/**
 * Serve a list of stock items read by product id from MSI.
 *
 * StockRegistryPreloader reads through this method and then seeds StockRegistryStorage with what it
 * got, so a list left on cataloginventory_stock_item does not merely answer with frozen data: it
 * plants that data in the registry every other read goes through afterwards.
 *
 * Only a criteria that names its products can be answered - it maps onto the index by sku. Anything
 * else, getLowStockItems() above all, is left to the original method.
 */
class AdaptGetStockItemListPlugin
{
    /**
     * @param StockRegistryProvider $stockRegistryProvider
     */
    public function __construct(
        private readonly StockRegistryProvider $stockRegistryProvider
    ) {
    }

    /**
     * Replace the collection items with the ones MSI holds for the same products.
     *
     * @param StockItemRepositoryInterface $subject
     * @param StockItemCollectionInterface $result
     * @param StockItemCriteriaInterface $criteria
     * @return StockItemCollectionInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetList(
        StockItemRepositoryInterface $subject,
        StockItemCollectionInterface $result,
        StockItemCriteriaInterface $criteria
    ): StockItemCollectionInterface {
        $productIds = $this->extractProductIds($criteria->getPart('products_filter')[0] ?? null);
        if (!$productIds) {
            return $result;
        }

        $scopeId = (int)($criteria->getPart('website_filter')[0] ?? 0);
        $stockItems = $this->stockRegistryProvider->getStockItems($productIds, $scopeId);

        $result->getItems();
        $result->setItems(array_values($stockItems));

        return $result;
    }

    /**
     * Read the product ids out of a products filter.
     *
     * @param mixed $products
     * @return int[]
     */
    private function extractProductIds(mixed $products): array
    {
        if ($products === null) {
            return [];
        }

        $productIds = [];
        foreach (is_array($products) ? $products : [$products] as $product) {
            $productId = $product instanceof ProductInterface ? $product->getId() : $product;
            if ($productId !== null && $productId !== '') {
                $productIds[] = (int)$productId;
            }
        }

        return $productIds;
    }
}
