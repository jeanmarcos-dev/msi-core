<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Magento\InventoryImportExport\Plugin\Import;

use Magento\CatalogImportExport\Model\StockItemProcessorInterface;
use Magento\InventoryConfiguration\Model\ResourceModel\StockItemConfiguration as StockItemConfigurationResource;

/**
 * Mirror imported legacy stock item rows into the MSI stock item configuration table.
 */
class StockItemConfigurationImporter
{
    private const STOCK_ROW_FIELDS = [
        'qty' => null,
        'is_in_stock' => null,
        'min_qty' => null,
        'use_config_min_qty' => null,
        'backorders' => null,
        'use_config_backorders' => null,
        'out_of_stock_qty' => null,
        'allow_backorders' => null,
    ];

    /**
     * @param StockItemConfigurationResource $stockItemConfigurationResource
     */
    public function __construct(
        private readonly StockItemConfigurationResource $stockItemConfigurationResource,
    ) {
    }

    /**
     * After plugin for StockItemProcessor::process to store the imported configuration in MSI
     *
     * @param StockItemProcessorInterface $subject
     * @param mixed $result
     * @param array $stockData
     * @param array $importedData
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterProcess(
        StockItemProcessorInterface $subject,
        mixed $result,
        array $stockData,
        array $importedData
    ): void {
        $rows = [];
        foreach ($stockData as $sku => $row) {
            $sku = (string)$sku;
            if (!$this->carriesStockColumn($importedData[$sku] ?? [])) {
                continue;
            }

            $configuration = [StockItemConfigurationResource::SKU => $sku];
            foreach (StockItemConfigurationResource::FIELDS as $field) {
                if (array_key_exists($field, $row)) {
                    $configuration[$field] = $row[$field];
                }
            }
            $rows[] = $configuration;
        }

        $this->stockItemConfigurationResource->save($rows);
    }

    /**
     * Tell whether an import row carries a usable stock column.
     *
     * @param array $importedRow
     * @return bool
     */
    private function carriesStockColumn(array $importedRow): bool
    {
        return (bool) array_filter(
            array_intersect_key($importedRow, self::STOCK_ROW_FIELDS),
            static fn ($value) => $value !== null && $value !== ''
        );
    }
}
