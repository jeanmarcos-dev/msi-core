<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfigurableProduct\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Collection\SalableProcessor;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable\Product\CollectionFactory;
use Magento\Framework\DB\Select;

/**
 * Tell which configurable products have a child that is enabled in, assigned to and salable for a store.
 */
class GetParentIdsWithSalableChildren
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param SalableProcessor $salableProcessor
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SalableProcessor $salableProcessor
    ) {
    }

    /**
     * Get the ids of the given parents that have at least one salable child in the store.
     *
     * @param ProductInterface[] $parents
     * @param int $storeId
     * @return int[]
     */
    public function execute(array $parents, int $storeId): array
    {
        if (!$parents) {
            return [];
        }

        $collection = $this->collectionFactory->create()->setFlag('product_children', true);
        foreach ($parents as $parent) {
            $collection->setProductFilter($parent);
        }
        $collection->addStoreFilter($storeId);
        $collection = $this->salableProcessor->process($collection);

        $select = $collection->getSelectCountSql();
        $select->reset(Select::COLUMNS)
            ->columns('link_table.parent_id')
            ->group('link_table.parent_id');

        return array_map('intval', $collection->getConnection()->fetchCol($select));
    }
}
