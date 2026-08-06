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
            $configuration = [StockItemConfigurationResource::SKU => (string)$sku];
            foreach (StockItemConfigurationResource::FIELDS as $field) {
                if (array_key_exists($field, $row)) {
                    $configuration[$field] = $row[$field];
                }
            }
            $rows[] = $configuration;
        }

        $this->stockItemConfigurationResource->save($rows);
    }
}
