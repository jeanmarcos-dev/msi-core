<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\CatalogInventory\Model\ResourceModel\Stock\Status;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Model\ResourceModel\Stock\Status;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryCatalog\Model\ResourceModel\AddIsInStockFilterToCollection;

/**
 * Adapt adding is in stock filter to collection for multi stocks.
 */
#[\AllowDynamicProperties]
class AdaptAddIsInStockFilterToCollectionPlugin
{
    /**
     * @var GetStockIdForCurrentWebsite
     */
    private GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite;

    /**
     * @var AddIsInStockFilterToCollection
     */
    private AddIsInStockFilterToCollection $addIsInStockFilterToCollection;

    /**
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param AddIsInStockFilterToCollection $addIsInStockFilterToCollection
     */
    public function __construct(
        GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        AddIsInStockFilterToCollection $addIsInStockFilterToCollection
    ) {
        $this->getStockIdForCurrentWebsite = $getStockIdForCurrentWebsite;
        $this->addIsInStockFilterToCollection = $addIsInStockFilterToCollection;
    }

    /**
     * Adapt adding is in stock filter to collection for multi stocks.
     *
     * @param Status $stockStatus
     * @param callable $proceed
     * @param Collection $collection
     * @return Status
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundAddIsInStockFilterToCollection(
        Status $stockStatus,
        callable $proceed,
        $collection
    ) {
        $stockId = $this->getStockIdForCurrentWebsite->execute();
        $this->addIsInStockFilterToCollection->execute($collection, $stockId);

        return $stockStatus;
    }
}
