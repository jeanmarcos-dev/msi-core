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
 * Serve the quantity_and_stock_status grid field from the MSI index.
 *
 * The persisted value of the attribute is the EAV default written when the product was created; core never
 * refreshes it, which is why the CatalogInventory strategy shadows it with a join on the legacy stock item.
 * That join now points at a frozen table, so the salability of the stock behind the grid scope takes over.
 */
class AddQuantityAndStockStatusFieldToCollection implements AddFieldToCollectionInterface
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
            'quantity_and_stock_status',
            $this->getStockIndexTableByStoreId->execute((int)$collection->getStoreId()),
            IndexStructure::IS_SALABLE,
            IndexStructure::SKU . '=sku',
            null,
            'left'
        );
    }
}
