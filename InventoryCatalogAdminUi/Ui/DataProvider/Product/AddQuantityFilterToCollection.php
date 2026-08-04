<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Ui\DataProvider\Product;

use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\Data\Collection;
use Magento\InventoryIndexer\Indexer\IndexStructure;
use Magento\Ui\DataProvider\AddFilterToCollectionInterface;

/**
 * Filter the product grid quantity range against the MSI index.
 *
 * Mirrors the CatalogInventory strategy, which reads the column the field strategy joined; the MSI index
 * names that column quantity instead of qty.
 */
class AddQuantityFilterToCollection implements AddFilterToCollectionInterface
{
    /**
     * @inheritdoc
     */
    public function addFilter(Collection $collection, $field, $condition = null)
    {
        $quantityField = AbstractCollection::ATTRIBUTE_TABLE_ALIAS_PREFIX . 'qty.' . IndexStructure::QUANTITY;

        if (isset($condition['gteq'])) {
            $collection->getSelect()->where($quantityField . ' >= ?', (float)$condition['gteq']);
        }
        if (isset($condition['lteq'])) {
            $collection->getSelect()->where($quantityField . ' <= ?', (float)$condition['lteq']);
        }
    }
}
