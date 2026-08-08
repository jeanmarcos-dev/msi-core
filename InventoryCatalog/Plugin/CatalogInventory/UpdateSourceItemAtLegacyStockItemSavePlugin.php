<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\CatalogInventory;

use Exception;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Item as ItemResourceModel;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Model\AbstractModel;
use Magento\InventoryCatalog\Model\GetDefaultSourceItemBySku;
use Magento\InventoryCatalog\Model\UpdateSourceItemBasedOnLegacyStockItem;
use Magento\InventoryCatalogApi\Model\CompositeProductStockStatusProcessorInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryConfiguration\Model\LegacyStockItem\CacheStorage;
use Magento\InventoryConfiguration\Model\ProjectLegacyStockItemToConfiguration;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryIndexer\Model\ReindexSourceItemsBySkus;

/**
 * Persist a legacy stock item save into MSI instead of cataloginventory_stock_item.
 *
 * The original resource save is deliberately not invoked: MSI owns both the configuration and the
 * quantity, so letting core write its own table would only produce a second copy that nothing reads.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class UpdateSourceItemAtLegacyStockItemSavePlugin
{
    /**
     * @var int
     */
    private int $recursionLevel = 0;

    /**
     * @param UpdateSourceItemBasedOnLegacyStockItem $updateSourceItemBasedOnLegacyStockItem
     * @param ResourceConnection $resourceConnection
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed
     * @param GetProductTypesBySkusInterface $getProductTypeBySku
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param GetDefaultSourceItemBySku $getDefaultSourceItemBySku
     * @param CacheStorage $stockItemCacheStorage
     * @param CompositeProductStockStatusProcessorInterface $compositeProductStockStatusProcessor
     * @param IsSingleSourceModeInterface $isSingleSourceMode
     * @param ProjectLegacyStockItemToConfiguration $projectLegacyStockItemToConfiguration
     * @param StockRegistryStorage $stockRegistryStorage
     * @param ReindexSourceItemsBySkus $reindexSourceItemsBySkus
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly UpdateSourceItemBasedOnLegacyStockItem $updateSourceItemBasedOnLegacyStockItem,
        private readonly ResourceConnection $resourceConnection,
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed,
        private readonly GetProductTypesBySkusInterface $getProductTypeBySku,
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly GetDefaultSourceItemBySku $getDefaultSourceItemBySku,
        private readonly CacheStorage $stockItemCacheStorage,
        private readonly CompositeProductStockStatusProcessorInterface $compositeProductStockStatusProcessor,
        private readonly IsSingleSourceModeInterface $isSingleSourceMode,
        private readonly ProjectLegacyStockItemToConfiguration $projectLegacyStockItemToConfiguration,
        private readonly StockRegistryStorage $stockRegistryStorage,
        private readonly ReindexSourceItemsBySkus $reindexSourceItemsBySkus
    ) {
    }

    /**
     * Update source item for legacy stock.
     *
     * @param ItemResourceModel $subject
     * @param callable $proceed
     * @param AbstractModel $legacyStockItem
     * @return ItemResourceModel
     * @throws Exception
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundSave(ItemResourceModel $subject, callable $proceed, AbstractModel $legacyStockItem)
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            /**
             * @var Item $legacyStockItem
             */
            $productId = $legacyStockItem->getProductId();
            $sku = $this->getSkusByProductIds->execute([$productId])[$productId];
            $typeId = $this->getProductTypeBySku->execute([$sku])[$sku];

            $this->stockItemCacheStorage->delete($sku);
            $configurationChanged = $this->projectLegacyStockItemToConfiguration->execute($sku, $legacyStockItem);

            $sourceItemSaved = false;
            if ($this->isSourceItemManagementAllowed->execute($typeId)
                && $this->shouldAlignDefaultSourceWithLegacy($legacyStockItem)
            ) {
                $sourceItemSaved = $this->updateSourceItemBasedOnLegacyStockItem->execute($legacyStockItem);
            }
            try {
                // Prevent recursion.
                // This should never happen as composite products cannot have composite products as children.
                $this->recursionLevel++;
                if ($this->recursionLevel === 1 && $this->isSingleSourceMode->execute()) {
                    $this->compositeProductStockStatusProcessor->execute([$sku]);
                }
            } finally {
                $this->recursionLevel--;
            }

            // The registry hands out the stock item it cached and the caller mutates that very object, so after
            // a save the cached copy carries caller data over a snapshot of what MSI reported before it. Left in
            // place, the next save of the same product would read those leftovers as a deliberate change and
            // project them onto the default source item.
            $this->stockRegistryStorage->removeStockItem($productId);
            $this->stockRegistryStorage->removeStockStatus($productId);

            $connection->commit();

            if ($configurationChanged && !$sourceItemSaved) {
                $this->reindexSourceItemsBySkus->execute([$sku]);
            }

            return $subject;
        } catch (Exception $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    /**
     * Return true if legacy stock item should update default source (if existing)
     *
     * @param Item $legacyStockItem
     * @return bool
     * @throws InputException
     */
    private function shouldAlignDefaultSourceWithLegacy(Item $legacyStockItem): bool
    {
        $productSku = $this->getSkusByProductIds
            ->execute([$legacyStockItem->getProductId()])[$legacyStockItem->getProductId()];

        $result = $legacyStockItem->getIsInStock() ||
            ((float) $legacyStockItem->getQty() !== (float) 0) ||
            ($this->getDefaultSourceItemBySku->execute($productSku) !== null);

        return $result;
    }
}
