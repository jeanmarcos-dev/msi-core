<?php
/**
 * Copyright 2024 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Observer;

use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogInventory\Model\StockItemValidator;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\InventoryCatalog\Model\Cache\ProductIdsBySkusStorage;
use Magento\InventoryCatalog\Model\Cache\ProductSkusByIdsStorage;
use Magento\InventoryCatalog\Model\Cache\ProductTypesBySkusStorage;
use Magento\InventoryCatalog\Model\Product\PersistedStatusStorage;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSourceItemsBySkus;

/**
 * This class extends the original SaveInventoryDataObserver to invalidate caches and ignore processing of parent stocks
 */
class SaveInventoryDataObserver extends \Magento\CatalogInventory\Observer\SaveInventoryDataObserver
{
    /**
     * @param ProductIdsBySkusStorage $productIdsBySkusStorage
     * @param ProductSkusByIdsStorage $productSkusByIdsStorage
     * @param ProductTypesBySkusStorage $productTypesBySkusStorage
     * @param ReindexSourceItemsBySkus $reindexSourceItemsBySkus
     * @param PersistedStatusStorage $persistedStatusStorage
     * @param StockConfigurationInterface $stockConfiguration
     * @param StockRegistryInterface $stockRegistry
     * @param StockItemValidator $stockItemValidator
     */
    public function __construct(
        private readonly ProductIdsBySkusStorage $productIdsBySkusStorage,
        private readonly ProductSkusByIdsStorage $productSkusByIdsStorage,
        private readonly ProductTypesBySkusStorage $productTypesBySkusStorage,
        private readonly ReindexSourceItemsBySkus $reindexSourceItemsBySkus,
        private readonly PersistedStatusStorage $persistedStatusStorage,
        StockConfigurationInterface $stockConfiguration,
        StockRegistryInterface $stockRegistry,
        StockItemValidator $stockItemValidator
    ) {
        // Ignore $parentItemProcessorPool as this logic is moved to a lower level to cover cases
        // when the stock item is saved separately
        // @see \Magento\InventoryCatalog\Plugin\CatalogInventory\UpdateSourceItemAtLegacyStockItemSavePlugin
        parent::__construct($stockConfiguration, $stockRegistry, $stockItemValidator);
    }

    /**
     * @inheritdoc
     */
    public function execute(EventObserver $observer)
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();
        $productId = (int) $product->getId();
        $sku = (string) $product->getSku();
        // Invalidate caches as the product sku and type could be changed
        // For example, simple product is converted to configurable product if children are added to it
        $this->productTypesBySkusStorage->delete($sku);
        $this->productIdsBySkusStorage->delete($sku);
        $this->productSkusByIdsStorage->delete($productId);

        // Composite salability counts enabled children only, so a status flip has to reindex every stock
        // holding the product. Saving the product alone only refreshes the stock behind the default source.
        if (!$product->isObjectNew() && $this->isStatusChanged($product)) {
            $this->reindexSourceItemsBySkus->execute([$sku]);
        }
        $this->persistedStatusStorage->delete($product);
        parent::execute($observer);
    }

    /**
     * Whether the product status differs from the one persisted for the scope it is written to
     *
     * @param Product $product
     * @return bool
     */
    private function isStatusChanged(Product $product): bool
    {
        $persistedStatus = $this->persistedStatusStorage->get($product);

        return $persistedStatus !== null && (int) $product->getStatus() !== $persistedStatus;
    }
}
