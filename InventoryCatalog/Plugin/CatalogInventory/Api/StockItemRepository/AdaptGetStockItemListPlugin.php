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
    private const NO_PRODUCT = 0;

    /**
     * @param StockRegistryProvider $stockRegistryProvider
     */
    public function __construct(
        private readonly StockRegistryProvider $stockRegistryProvider
    ) {
    }

    /**
     * Answer a list of stock items out of MSI instead of cataloginventory_stock_item.
     *
     * The original method is still called, but with a products filter that matches nothing: the collection
     * it returns is the contract the caller expects, and loading it that way costs one empty query instead
     * of a row and an object per product that would be thrown away right afterwards.
     *
     * @param StockItemRepositoryInterface $subject
     * @param callable $proceed
     * @param StockItemCriteriaInterface $criteria
     * @return StockItemCollectionInterface
     */
    public function aroundGetList(
        StockItemRepositoryInterface $subject,
        callable $proceed,
        StockItemCriteriaInterface $criteria
    ): StockItemCollectionInterface {
        $productIds = $this->extractProductIds($criteria->getPart('products_filter')[0] ?? null);
        if (!$productIds) {
            return $proceed($criteria);
        }

        $scopeId = (int)($criteria->getPart('website_filter')[0] ?? 0);
        $stockItems = $this->stockRegistryProvider->getStockItems($productIds, $scopeId);

        $unmatchable = clone $criteria;
        $unmatchable->setProductsFilter(self::NO_PRODUCT);
        $result = $proceed($unmatchable);

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
