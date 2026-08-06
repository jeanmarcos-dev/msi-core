<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Ui\DataProvider\Product;

use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Framework\Data\Collection;
use Magento\InventoryCatalog\Model\GetStockIndexTableByStoreId;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\Ui\DataProvider\AddFieldToCollectionInterface;

/**
 * Serve the product grid quantity column from the MSI index.
 *
 * Replaces the CatalogInventory strategy, which joins cataloginventory_stock_item on stock id 1. That table
 * is no longer written, so the column would show whatever it was frozen at.
 *
 * The join keeps going through joinField because the grid sorts by the column, and the collection only
 * knows how to order by fields it registered itself.
 */
class AddQuantityFieldToCollection implements AddFieldToCollectionInterface
{
    /**
     * @param GetStockIndexTableByStoreId $getStockIndexTableByStoreId
     */
    public function __construct(
        private readonly GetStockIndexTableByStoreId $getStockIndexTableByStoreId
    ) {
    }

    /**
     * @inheritdoc
     */
    public function addField(Collection $collection, $field, $alias = null)
    {
        /** @var ProductCollection $collection */
        $collection->joinField(
            'qty',
            $this->getStockIndexTableByStoreId->execute((int)$collection->getStoreId()),
            IndexStructure::QUANTITY,
            IndexStructure::SKU . '=sku',
            null,
            'left'
        );
    }
}
