<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Resolve the MSI index table serving a store, for grids scoped by store id.
 *
 * Resolution goes through the website sales channel rather than through the stock id directly, so the
 * admin store lands on whatever stock the admin website is adapted to instead of on a hardcoded id.
 */
class GetStockIndexTableByStoreId
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        private readonly StockIndexTableNameResolverInterface $stockIndexTableNameResolver
    ) {
    }

    /**
     * Get the index table name of the stock assigned to the website of the given store.
     *
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function execute(int $storeId): string
    {
        $websiteId = (int)$this->storeManager->getStore($storeId)->getWebsiteId();
        $stockId = (int)$this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();

        return $this->stockIndexTableNameResolver->execute($stockId);
    }
}
