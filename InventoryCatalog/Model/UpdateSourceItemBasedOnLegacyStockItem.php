<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Model\Stock\Item;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;

class UpdateSourceItemBasedOnLegacyStockItem
{
    /**
     * @var SourceItemInterfaceFactory
     */
    private $sourceItemFactory;

    /**
     * @var SourceItemsSaveInterface
     */
    private $sourceItemsSave;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /**
     * @var GetSkusByProductIdsInterface
     */
    private $getSkusByProductIds;

    /**
     * @var GetDefaultSourceItemBySku
     */
    private $getDefaultSourceItemBySku;

    /**
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param GetDefaultSourceItemBySku $getDefaultSourceItemBySku
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSave,
        DefaultSourceProviderInterface $defaultSourceProvider,
        GetSkusByProductIdsInterface $getSkusByProductIds,
        GetDefaultSourceItemBySku $getDefaultSourceItemBySku
    ) {
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSave = $sourceItemsSave;
        $this->getSkusByProductIds = $getSkusByProductIds;
        $this->getDefaultSourceItemBySku = $getDefaultSourceItemBySku;
        $this->defaultSourceProvider = $defaultSourceProvider;
    }

    /**
     * Project a legacy stock item save onto the default source item.
     *
     * Only the fields the caller actually changed are projected. The legacy stock item is hydrated from the
     * MSI index, whose quantity is the sum over every source of the stock, so writing it back unconditionally
     * would copy that aggregate into the default source and inflate the stock on any save that never meant to
     * touch quantities. A stock item with no original data was built by the caller (import, product create),
     * and is taken at face value.
     *
     * @param Item $legacyStockItem
     * @return bool whether a source item was saved, and with it the reindex that follows one
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function execute(Item $legacyStockItem): bool
    {
        $productSku = $this->getSkusByProductIds
            ->execute([$legacyStockItem->getProductId()])[$legacyStockItem->getProductId()];

        $sourceItem = $this->getDefaultSourceItemBySku->execute($productSku);
        $isNewSourceItem = $sourceItem === null;
        $applyQty = $isNewSourceItem || $this->isChangedByCaller($legacyStockItem, StockItemInterface::QTY);
        $applyStatus = $isNewSourceItem
            || $this->isChangedByCaller($legacyStockItem, StockItemInterface::IS_IN_STOCK);

        if (!$applyQty && !$applyStatus) {
            return false;
        }

        if ($isNewSourceItem) {
            /** @var SourceItemInterface $sourceItem */
            $sourceItem = $this->sourceItemFactory->create();
            $sourceItem->setSourceCode($this->defaultSourceProvider->getCode());
            $sourceItem->setSku($productSku);
        }

        if ($applyQty) {
            $sourceItem->setQuantity((float)$legacyStockItem->getQty());
        }
        if ($applyStatus) {
            $sourceItem->setStatus((int)$legacyStockItem->getIsInStock());
        }

        $this->sourceItemsSave->execute([$sourceItem]);

        return true;
    }

    /**
     * Whether the caller set a field to something other than what the stock item was hydrated with.
     *
     * @param Item $legacyStockItem
     * @param string $field
     * @return bool
     */
    private function isChangedByCaller(Item $legacyStockItem, string $field): bool
    {
        if (!$legacyStockItem->hasData($field)) {
            return false;
        }

        $originalValue = $legacyStockItem->getOrigData($field);

        return $originalValue === null
            || (float)$originalValue !== (float)$legacyStockItem->getData($field);
    }
}
