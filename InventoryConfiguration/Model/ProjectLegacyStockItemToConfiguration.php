<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryConfiguration\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;
use Magento\InventoryConfiguration\Model\StockItemConfiguration\CacheStorage;

/**
 * Mirror a legacy stock item entity into the MSI stock item configuration table.
 *
 * Core still owns the admin product form and the import, both of which write
 * cataloginventory_stock_item directly. This keeps MSI's own table authoritative
 * without waiting for those write paths to be replaced.
 */
class ProjectLegacyStockItemToConfiguration
{
    /**
     * @var StockItemConfigurationResource
     */
    private $stockItemConfigurationResource;

    /**
     * @var CacheStorage
     */
    private $cacheStorage;

    /**
     * @param StockItemConfigurationResource $stockItemConfigurationResource
     * @param CacheStorage $cacheStorage
     */
    public function __construct(
        StockItemConfigurationResource $stockItemConfigurationResource,
        CacheStorage $cacheStorage
    ) {
        $this->stockItemConfigurationResource = $stockItemConfigurationResource;
        $this->cacheStorage = $cacheStorage;
    }

    /**
     * Fields the stock index reads to decide whether a row is salable.
     *
     * @var string[]
     */
    private const FIELDS_THE_INDEX_READS = [
        'manage_stock',
        'use_config_manage_stock',
        'backorders',
        'use_config_backorders',
        'min_qty',
        'use_config_min_qty',
    ];

    /**
     * Mirror a legacy stock item entity into the MSI stock item configuration table.
     *
     * @param string $sku
     * @param AbstractModel $legacyStockItem
     * @return bool whether a field the stock index reads changed, and with it the index the caller holds
     */
    public function execute(string $sku, AbstractModel $legacyStockItem): bool
    {
        $data = $legacyStockItem->getData();
        $row = [StockItemConfigurationResource::SKU => $sku];
        foreach (StockItemConfigurationResource::FIELDS as $field) {
            // Fields the caller never set keep their column default on insert, and their value on update.
            if (array_key_exists($field, $data)) {
                $row[$field] = $data[$field];
            }
        }

        $this->stockItemConfigurationResource->save([$row]);
        $this->cacheStorage->delete($sku);

        return $this->changedWhatTheIndexReads($legacyStockItem, $data);
    }

    /**
     * Whether the caller moved any of the fields the stock index resolves when it is built.
     *
     * @param AbstractModel $legacyStockItem
     * @param array $data
     * @return bool
     */
    private function changedWhatTheIndexReads(AbstractModel $legacyStockItem, array $data): bool
    {
        foreach (self::FIELDS_THE_INDEX_READS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $originalValue = $legacyStockItem->getOrigData($field);
            if ($originalValue === null || (string)$originalValue !== (string)$data[$field]) {
                return true;
            }
        }

        return false;
    }
}
