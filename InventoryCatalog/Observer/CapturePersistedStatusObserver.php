<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Observer;

use Magento\Catalog\Model\Product;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryCatalog\Model\Product\PersistedStatusStorage;
use Magento\InventoryCatalog\Model\ResourceModel\GetPersistedProductStatus;

class CapturePersistedStatusObserver implements ObserverInterface
{
    /**
     * @param GetPersistedProductStatus $getPersistedProductStatus
     * @param PersistedStatusStorage $persistedStatusStorage
     */
    public function __construct(
        private readonly GetPersistedProductStatus $getPersistedProductStatus,
        private readonly PersistedStatusStorage $persistedStatusStorage
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();
        if (!$product->isObjectNew()) {
            $this->persistedStatusStorage->set($product, $this->getPersistedProductStatus->execute($product));
        }
    }
}
