<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryBundleProduct\Plugin\Bundle\Model\ResourceModel\Selection\Collection;

use Magento\Bundle\Model\ResourceModel\Selection\Collection;
use Magento\InventorySalesApi\Api\AreProductsSalableInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Adapt add quantity filter to bundle selection in multi stock environment plugin.
 */
class AdaptAddQuantityFilterPlugin
{
    /**
     * @var AreProductsSalableInterface
     */
    private $areProductsSalable;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StockByWebsiteIdResolverInterface
     */
    private $stockByWebsiteIdResolver;

    /**
     * @param AreProductsSalableInterface $areProductsSalable
     * @param StoreManagerInterface $storeManager
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     */
    public function __construct(
        AreProductsSalableInterface $areProductsSalable,
        StoreManagerInterface $storeManager,
        StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
    ) {
        $this->areProductsSalable = $areProductsSalable;
        $this->storeManager = $storeManager;
        $this->stockByWebsiteIdResolver = $stockByWebsiteIdResolver;
    }

    /**
     * Keep only the selections salable in the stock of the current website.
     *
     * The original filter inner joins cataloginventory_stock_item, so a selection product with no row there
     * - every product created after MSI took over the write path - drops out of the bundle altogether.
     *
     * @param Collection $subject
     * @param \Closure $proceed
     * @return Collection
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundAddQuantityFilter(
        Collection $subject,
        \Closure $proceed
    ): Collection {
        $website = $this->storeManager->getWebsite();
        $stock = $this->stockByWebsiteIdResolver->execute((int)$website->getId());
        $skus = [];
        $skusToExclude = [];
        foreach ($subject->getData() as $item) {
            $skus[] = (string)$item['sku'];
        }
        $results = $this->areProductsSalable->execute($skus, $stock->getStockId());
        foreach ($results as $result) {
            if (!$result->isSalable()) {
                $skusToExclude[] = $result->getSku();
            }
        }
        if ($skusToExclude) {
            // The array has to reach where() unflattened, or it is quoted as one string and matches nothing.
            $subject->getSelect()->where('e.sku NOT IN(?)', $skusToExclude);
        }
        $subject->resetData();

        return $subject;
    }
}
