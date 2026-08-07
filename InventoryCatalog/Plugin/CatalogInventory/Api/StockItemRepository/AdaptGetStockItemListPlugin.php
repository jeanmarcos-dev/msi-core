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
use Magento\InventoryCatalog\Model\StockItemCollectionFactory;
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
     * @param StockItemCollectionFactory $stockItemCollectionFactory
     */
    public function __construct(
        private readonly StockRegistryProvider $stockRegistryProvider,
        private readonly StockItemCollectionFactory $stockItemCollectionFactory
    ) {
    }

    /**
     * Answer a list of stock items out of MSI instead of cataloginventory_stock_item.
     *
     * The original method is not called at all when the criteria names its products: every row it would
     * fetch is one this plugin replaces, so running that query only to discard its result is waste.
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
        $stockItems = array_values($this->stockRegistryProvider->getStockItems($productIds, $scopeId));

        $collection = $this->stockItemCollectionFactory->create();
        $collection->setItems($stockItems);
        $collection->setTotalCount(count($stockItems));

        return $collection;
    }

    /**
     * Read the product ids out of a products filter.
     *
     * Callers hand this filter whatever they have: a bare id, a product, a flat list, or the grouped
     * lists Configurable::getChildrenIds() returns. Casting a group to int would silently yield 1 and
     * answer about a product nobody asked for, so groups are walked and anything that is not a number
     * is dropped.
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
            if (is_array($product)) {
                $productIds[] = $this->extractProductIds($product);
                continue;
            }
            $productId = $product instanceof ProductInterface ? $product->getId() : $product;
            if (is_numeric($productId)) {
                $productIds[] = [(int)$productId];
            }
        }

        return array_values(array_unique(array_merge([], ...$productIds)));
    }
}
