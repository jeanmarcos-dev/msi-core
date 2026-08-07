<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\CatalogInventory\Api\Data\StockInterface;
use Magento\CatalogInventory\Api\Data\StockInterfaceFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\Data\StockItemInterfaceFactory;
use Magento\CatalogInventory\Api\Data\StockStatusInterface;
use Magento\CatalogInventory\Api\Data\StockStatusInterfaceFactory;
use Magento\CatalogInventory\Api\StockCriteriaInterfaceFactory;
use Magento\CatalogInventory\Api\StockRepositoryInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfiguration\Model\GetStockItemsConfigurationInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;

/**
 * Serve the legacy stock registry from MSI.
 *
 * Every legacy read contract in core funnels through this SPI, so pointing it at the MSI index and
 * at inventory_stock_item_configuration retires cataloginventory_stock_item as a read source without
 * having to touch each consumer.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class StockRegistryProvider implements StockRegistryProviderInterface
{
    /**
     * @param StockRepositoryInterface $stockRepository
     * @param StockInterfaceFactory $stockFactory
     * @param StockCriteriaInterfaceFactory $stockCriteriaFactory
     * @param StockItemInterfaceFactory $stockItemFactory
     * @param StockStatusInterfaceFactory $stockStatusFactory
     * @param StockRegistryStorage $stockRegistryStorage
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param GetStockItemsConfigurationInterface $getStockItemsConfiguration
     * @param GetStockItemDataInterface $getStockItemData
     * @param GetStockItemsDataInterface $getStockItemsData
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly StockRepositoryInterface $stockRepository,
        private readonly StockInterfaceFactory $stockFactory,
        private readonly StockCriteriaInterfaceFactory $stockCriteriaFactory,
        private readonly StockItemInterfaceFactory $stockItemFactory,
        private readonly StockStatusInterfaceFactory $stockStatusFactory,
        private readonly StockRegistryStorage $stockRegistryStorage,
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly GetStockItemsConfigurationInterface $getStockItemsConfiguration,
        private readonly GetStockItemDataInterface $getStockItemData,
        private readonly GetStockItemsDataInterface $getStockItemsData,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getStock($scopeId)
    {
        $stock = $this->stockRegistryStorage->getStock($scopeId);
        if (null !== $stock) {
            return $stock;
        }

        $criteria = $this->stockCriteriaFactory->create();
        $criteria->setScopeFilter($scopeId);
        $stock = current($this->stockRepository->getList($criteria)->getItems());
        if ($stock && $stock->getStockId()) {
            $this->stockRegistryStorage->setStock($scopeId, $stock);
            return $stock;
        }

        return $this->stockFactory->create();
    }

    /**
     * @inheritdoc
     */
    public function getStockItem($productId, $scopeId)
    {
        // The SPI is untyped but the registry storage is not, and it rejects the string ids the indexer passes.
        $productId = (int)$productId;
        $scopeId = (int)$scopeId;

        $stockItem = $this->stockRegistryStorage->getStockItem($productId, $scopeId);
        if (null !== $stockItem) {
            return $stockItem;
        }

        $sku = $this->getSku($productId);
        if (null === $sku) {
            return $this->stockItemFactory->create();
        }

        $stockId = (int)$this->stockByWebsiteIdResolver->execute($scopeId)->getStockId();
        $stockItem = $this->buildStockItem($sku, $productId, $stockId, $scopeId);
        $this->stockRegistryStorage->setStockItem($productId, $scopeId, $stockItem);

        return $stockItem;
    }

    /**
     * @inheritdoc
     */
    public function getStockStatus($productId, $scopeId)
    {
        $productId = (int)$productId;
        $scopeId = (int)$scopeId;

        $stockStatus = $this->stockRegistryStorage->getStockStatus($productId, $scopeId);
        if (null !== $stockStatus) {
            return $stockStatus;
        }

        $sku = $this->getSku($productId);
        if (null === $sku) {
            return $this->stockStatusFactory->create();
        }

        $stockId = (int)$this->stockByWebsiteIdResolver->execute($scopeId)->getStockId();
        $indexData = $this->getIndexData($sku, $stockId);

        /** @var StockStatusInterface $stockStatus */
        $stockStatus = $this->stockStatusFactory->create();
        $stockStatus->setProductId($productId);
        $stockStatus->setStockId($stockId);
        $stockStatus->setQty((float)($indexData[GetStockItemDataInterface::QUANTITY] ?? 0));
        $stockStatus->setStockStatus((int)($indexData[GetStockItemDataInterface::IS_SALABLE] ?? 0));
        $this->stockRegistryStorage->setStockStatus($productId, $scopeId, $stockStatus);

        return $stockStatus;
    }

    /**
     * Assemble the legacy stock items of several products at once.
     *
     * The callers that need this are bulk by nature - a quote loading its lines, a grid loading a page -
     * so the whole set is resolved in three queries regardless of how many products it covers. Serving
     * them one by one through getStockItem() would issue two per product instead.
     *
     * @param int[] $productIds
     * @param int $scopeId
     * @return StockItemInterface[] keyed by product id
     */
    public function getStockItems(array $productIds, int $scopeId): array
    {
        $productIds = array_map('intval', $productIds);
        $skus = $this->getSkus($productIds);
        if (!$skus) {
            return [];
        }

        $stockId = (int)$this->stockByWebsiteIdResolver->execute($scopeId)->getStockId();
        $configurations = $this->getStockItemsConfiguration->execute(array_values($skus));
        $indexData = $this->getIndexDataOfSkus(array_values($skus), $stockId);

        $stockItems = [];
        foreach ($skus as $productId => $sku) {
            $stockItems[$productId] = $this->hydrateStockItem(
                $productId,
                $stockId,
                $scopeId,
                $configurations[$sku] ?? null,
                $indexData[$sku] ?? null
            );
        }

        return $stockItems;
    }

    /**
     * Assemble a legacy stock item out of the MSI configuration and index.
     *
     * @param string $sku
     * @param int $productId
     * @param int $stockId
     * @param int $scopeId
     * @return StockItemInterface
     */
    private function buildStockItem(string $sku, int $productId, int $stockId, int $scopeId): StockItemInterface
    {
        return $this->hydrateStockItem(
            $productId,
            $stockId,
            $scopeId,
            $this->getStockItemsConfiguration->execute([$sku])[$sku] ?? null,
            $this->getIndexData($sku, $stockId)
        );
    }

    /**
     * Shape the MSI configuration and index row of one product into a legacy stock item.
     *
     * @param int $productId
     * @param int $stockId
     * @param int $scopeId
     * @param mixed $configuration
     * @param array|null $indexData
     * @return StockItemInterface
     */
    private function hydrateStockItem(
        int $productId,
        int $stockId,
        int $scopeId,
        $configuration,
        ?array $indexData
    ): StockItemInterface {
        /** @var StockItemInterface $stockItem */
        $stockItem = $this->stockItemFactory->create(
            ['data' => $configuration ? $configuration->getData() : []]
        );
        // The registry storage only caches items carrying an id, and the row is keyed by sku in MSI.
        $stockItem->setItemId($productId);
        $stockItem->setProductId($productId);
        $stockItem->setStockId($stockId);
        $stockItem->setWebsiteId($scopeId);

        // A sku the index does not carry is not stocked anywhere in this stock, so it reads as out of stock
        // rather than as whatever the configuration's own flag was left saying — the same answer
        // getStockStatus() gives, and the two must not disagree about the same product.
        $stockItem->setQty((float)($indexData[GetStockItemDataInterface::QUANTITY] ?? 0));
        $stockItem->setIsInStock((bool)(int)($indexData[GetStockItemDataInterface::IS_SALABLE] ?? 0));
        // Snapshot what MSI reported, so the write bridge can tell a field the caller actually set from one it
        // merely read back. Without it, saving an unrelated field would push the index quantity - an aggregate
        // over every source of the stock - into the default source item.
        $stockItem->setOrigData();
        if ($stockItem instanceof AbstractModel) {
            // Hydrating through setters leaves the item looking modified, and the legacy save path reads that
            // as "the caller changed the stock item", overwriting the values it was actually given with these.
            $stockItem->setDataChanges(false);
        }

        return $stockItem;
    }

    /**
     * Read the MSI index row of a sku, tolerating stocks whose index is not built yet.
     *
     * @param string $sku
     * @param int $stockId
     * @return array|null
     */
    private function getIndexData(string $sku, int $stockId): ?array
    {
        try {
            return $this->getStockItemData->execute($sku, $stockId);
        } catch (LocalizedException $e) {
            return null;
        }
    }

    /**
     * Read the MSI index rows of several skus, tolerating stocks whose index is not built yet.
     *
     * @param string[] $skus
     * @param int $stockId
     * @return array
     */
    private function getIndexDataOfSkus(array $skus, int $stockId): array
    {
        try {
            return $this->getStockItemsData->execute($skus, $stockId) ?? [];
        } catch (LocalizedException $e) {
            return [];
        }
    }

    /**
     * Resolve the sku of a product id, or null when the product is gone.
     *
     * @param int $productId
     * @return string|null
     */
    private function getSku(int $productId): ?string
    {
        try {
            return $this->getSkusByProductIds->execute([$productId])[$productId] ?? null;
        } catch (LocalizedException $e) {
            return null;
        }
    }

    /**
     * Resolve the skus of several product ids, dropping the ones whose product is gone.
     *
     * @param int[] $productIds
     * @return string[] keyed by product id
     */
    private function getSkus(array $productIds): array
    {
        try {
            return $this->getSkusByProductIds->execute($productIds);
        } catch (LocalizedException $e) {
            return [];
        }
    }
}
