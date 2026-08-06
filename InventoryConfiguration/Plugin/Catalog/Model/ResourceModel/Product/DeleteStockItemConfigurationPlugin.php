<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Plugin\Catalog\Model\ResourceModel\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;

/**
 * Drop the stock item configuration of a deleted product.
 *
 * The configuration is keyed by sku and therefore cannot carry a foreign key to the catalog, so nothing
 * removes it on its own - unlike the legacy table it replaced, which cascaded off the product row.
 * A leftover row would then be inherited by the next product created with the same sku.
 */
class DeleteStockItemConfigurationPlugin
{
    /**
     * @param StockItemConfigurationResource $stockItemConfigurationResource
     * @param CacheStorage $cacheStorage
     */
    public function __construct(
        private readonly StockItemConfigurationResource $stockItemConfigurationResource,
        private readonly CacheStorage $cacheStorage
    ) {
    }

    /**
     * Remove the configuration row of the deleted product.
     *
     * @param ProductResource $subject
     * @param ProductResource $result
     * @param ProductInterface $product
     * @return ProductResource
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterDelete(ProductResource $subject, $result, $product): ProductResource
    {
        $sku = (string) $product->getSku();
        if ($sku === '') {
            return $result;
        }

        $this->stockItemConfigurationResource->deleteBySkus([$sku]);
        $this->cacheStorage->delete($sku);

        return $result;
    }
}
