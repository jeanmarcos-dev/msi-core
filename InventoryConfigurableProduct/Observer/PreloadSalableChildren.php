<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Observer;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryConfigurableProduct\Model\SalableChildren;

/**
 * Resolve the salable children of every configurable product of a loaded collection with a single query.
 */
class PreloadSalableChildren implements ObserverInterface
{
    /**
     * @param SalableChildren $salableChildren
     */
    public function __construct(private readonly SalableChildren $salableChildren)
    {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        $collection = $observer->getEvent()->getCollection();
        $storeId = (int)$collection->getStoreId();
        if ($storeId === 0) {
            return;
        }

        $parents = [];
        foreach ($collection->getItems() as $item) {
            if ($item->getTypeId() === Configurable::TYPE_CODE && !$this->isRowZero($item)) {
                $parents[] = $item;
            }
        }
        if ($parents) {
            $this->salableChildren->preload($parents, $storeId);
        }
    }

    /**
     * Check whether the stock index row of the item says it is not salable, which needs no further check.
     *
     * @param Product $item
     * @return bool
     */
    private function isRowZero(Product $item): bool
    {
        return $item->hasData('is_salable') && !(int)$item->getData('is_salable');
    }
}
