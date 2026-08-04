<?php
/**
 * Copyright 2025 jeanmarcos-dev
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Plugin\Catalog\Model\ResourceModel\Product\Collection;

use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\InventoryCatalog\Model\GetStockIndexTableByStoreId;
use Magento\InventoryIndexer\Indexer\IndexStructure;

/**
 * Point collection joins on cataloginventory_stock_item at the MSI index instead.
 *
 * The admin widget grids - the product pickers of reviews, url rewrites, bundle options and downloadable -
 * build their Quantity column with joinField() on the legacy table, which is no longer written. Their
 * _prepareCollection() is protected, so the collection itself is the only seam.
 */
class RedirectLegacyStockItemJoinPlugin
{
    private const LEGACY_TABLE = 'cataloginventory_stock_item';

    /**
     * Legacy column to its MSI index counterpart. Anything else has no equivalent and is left alone.
     */
    private const FIELD_MAP = [
        'qty' => IndexStructure::QUANTITY,
        'is_in_stock' => IndexStructure::IS_SALABLE,
    ];

    /**
     * @param GetStockIndexTableByStoreId $getStockIndexTableByStoreId
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        private readonly GetStockIndexTableByStoreId $getStockIndexTableByStoreId
    ) {
    }

    /**
     * Rewrite the table, column and binding of a legacy stock item join.
     *
     * @param Collection $subject
     * @param string $alias
     * @param string $table
     * @param string $field
     * @param string $bind
     * @param string|null $cond
     * @param string $joinType
     * @return array|null
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function beforeJoinField(
        Collection $subject,
        $alias,
        $table,
        $field,
        $bind,
        $cond = null,
        $joinType = 'inner'
    ): ?array {
        if ($table !== self::LEGACY_TABLE || !isset(self::FIELD_MAP[$field])) {
            return null;
        }

        return [
            $alias,
            $this->getStockIndexTableByStoreId->execute((int)$subject->getStoreId()),
            self::FIELD_MAP[$field],
            IndexStructure::SKU . '=sku',
            // The original condition scopes the join to a stock id, which the index table already is.
            null,
            $joinType,
        ];
    }
}
