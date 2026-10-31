<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Plugin\Model\Product\Type\Configurable;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolve configurable salability from the stock index row and one batched check of the children of the website.
 */
class IsSalablePlugin
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param SalableChildren $salableChildren
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly SalableChildren $salableChildren
    ) {
    }

    /**
     * Replace the per-product salable children count with the stock index row and the batched children check.
     *
     * @param Configurable $subject
     * @param callable $proceed
     * @param ProductInterface $product
     * @return bool
     */
    public function aroundIsSalable(Configurable $subject, callable $proceed, $product): bool
    {
        try {
            if (!$product->hasData('is_salable') || !$this->isCurrentStoreScope($subject, $product)) {
                return (bool)$proceed($product);
            }
            $storeId = (int)$this->storeManager->getStore()->getId();
        } catch (\Throwable $exception) {
            return (bool)$proceed($product);
        }

        if ($product->getStatus() != Status::STATUS_ENABLED || !(int)$product->getData('is_salable')) {
            return false;
        }

        return $this->salableChildren->exist($product, $storeId);
    }

    /**
     * Whether the salability being asked for is the one of the current store.
     *
     * @param Configurable $subject
     * @param ProductInterface $product
     * @return bool
     */
    private function isCurrentStoreScope(Configurable $subject, $product): bool
    {
        $storeFilter = $subject->getStoreFilter($product);
        if ($storeFilter instanceof Store) {
            $scopeStoreId = $storeFilter->getId();
        } elseif ($storeFilter !== null) {
            $scopeStoreId = $storeFilter;
        } else {
            $scopeStoreId = $product->getStoreId();
        }

        if ($scopeStoreId === null || $scopeStoreId === '') {
            return false;
        }

        return (int)$scopeStoreId === (int)$this->storeManager->getStore()->getId();
    }
}
