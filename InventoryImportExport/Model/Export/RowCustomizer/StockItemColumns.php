<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Model\Export\RowCustomizer;

use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;
use Magento\CatalogImportExport\Model\Export\RowCustomizerInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryConfiguration\Model\GetStockItemsConfigurationInterface;
use Magento\InventorySalesApi\Model\GetStockItemsDataInterface;
use Magento\InventorySalesApi\Model\StockByWebsiteIdResolverInterface;

/**
 * Fill the stock columns of the product export from MSI.
 *
 * The exporter reads them straight out of cataloginventory_stock_item, which is no longer written: products
 * created after the cut have no row there at all, and a row-less export drops the columns from the header too.
 * Emitting the same column set keeps the file importable by the stock item importer.
 *
 * The quantity is the one of the stock behind the export scope, matching what every legacy read contract now
 * reports for that scope. Per source quantities have their own dedicated import/export entity.
 */
class StockItemColumns implements RowCustomizerInterface
{
    /**
     * Stock columns of the legacy exporter, in its own order.
     */
    private const COLUMNS = [
        'qty',
        'min_qty',
        'use_config_min_qty',
        'is_qty_decimal',
        'backorders',
        'use_config_backorders',
        'min_sale_qty',
        'use_config_min_sale_qty',
        'max_sale_qty',
        'use_config_max_sale_qty',
        'is_in_stock',
        'notify_stock_qty',
        'use_config_notify_stock_qty',
        'manage_stock',
        'use_config_manage_stock',
        'use_config_qty_increments',
        'qty_increments',
        'use_config_enable_qty_inc',
        'enable_qty_increments',
        'is_decimal_divided',
        'website_id',
    ];

    /**
     * @var array
     */
    private $rowsByProductId = [];

    /**
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param GetStockItemsConfigurationInterface $getStockItemsConfiguration
     * @param GetStockItemsDataInterface $getStockItemsData
     * @param StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver
     * @param StockConfigurationInterface $stockConfiguration
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function __construct(
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly GetStockItemsConfigurationInterface $getStockItemsConfiguration,
        private readonly GetStockItemsDataInterface $getStockItemsData,
        private readonly StockByWebsiteIdResolverInterface $stockByWebsiteIdResolver,
        private readonly StockConfigurationInterface $stockConfiguration
    ) {
    }

    /**
     * @inheritdoc
     */
    public function prepareData($collection, $productIds)
    {
        $this->rowsByProductId = [];
        if (!$productIds) {
            return;
        }

        $skusByProductId = $this->getSkusByProductIds->execute($productIds);
        $skus = array_values($skusByProductId);
        $websiteId = (int)$this->stockConfiguration->getDefaultScopeId();
        $stockId = (int)$this->stockByWebsiteIdResolver->execute($websiteId)->getStockId();

        $configurations = $this->getStockItemsConfiguration->execute($skus);
        $indexData = $this->getStockItemsData->execute($skus, $stockId) ?? [];

        foreach ($skusByProductId as $productId => $sku) {
            $configuration = $configurations[$sku] ?? null;
            if ($configuration === null) {
                continue;
            }
            $this->rowsByProductId[$productId] = $this->buildRow(
                $configuration,
                $indexData[$sku] ?? null,
                $websiteId
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function addHeaderColumns($columns)
    {
        return array_values(array_unique(array_merge($columns, self::COLUMNS)));
    }

    /**
     * @inheritdoc
     */
    public function addData($dataRow, $productId)
    {
        if (isset($this->rowsByProductId[$productId])) {
            $dataRow = array_merge($dataRow, $this->rowsByProductId[$productId]);
        }

        return $dataRow;
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalRowsCount($additionalRowsCount, $productId)
    {
        return $additionalRowsCount;
    }

    /**
     * Assemble the stock columns of one product.
     *
     * @param StockItemInterface $configuration
     * @param array|null $indexData
     * @param int $websiteId
     * @return array
     */
    private function buildRow(StockItemInterface $configuration, ?array $indexData, int $websiteId): array
    {
        $row = [];
        foreach (self::COLUMNS as $column) {
            $row[$column] = $configuration->getData($column);
        }

        $row['qty'] = (float)($indexData[GetStockItemsDataInterface::QUANTITY] ?? 0);
        $row['is_in_stock'] = (int)($indexData[GetStockItemsDataInterface::IS_SALABLE] ?? 0);
        $row['website_id'] = $websiteId;

        // The exporter resolves the config-backed fields itself, so the file carries effective values.
        if ($row['use_config_max_sale_qty']) {
            $row['max_sale_qty'] = $this->stockConfiguration->getMaxSaleQty();
        }
        if ($row['use_config_min_sale_qty']) {
            $row['min_sale_qty'] = $this->stockConfiguration->getMinSaleQty();
        }
        if ($row['use_config_manage_stock']) {
            $row['manage_stock'] = $this->stockConfiguration->getManageStock();
        }

        return $row;
    }
}
