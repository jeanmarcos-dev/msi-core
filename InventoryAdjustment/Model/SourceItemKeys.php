<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Model;

use Magento\InventoryApi\Api\Data\SourceItemInterface;

class SourceItemKeys
{
    /**
     * Source code and SKU of every given source item
     *
     * @param SourceItemInterface[] $sourceItems
     * @return array
     */
    public function fromSourceItems(array $sourceItems): array
    {
        $keys = [];
        foreach ($sourceItems as $sourceItem) {
            $keys[] = [
                'source_code' => (string)$sourceItem->getSourceCode(),
                'sku' => (string)$sourceItem->getSku(),
            ];
        }

        return $keys;
    }

    /**
     * Source code and SKU of every row
     *
     * @param array $rows
     * @return array
     */
    public function fromRows(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $keys[] = [
                'source_code' => (string)$row[SourceItemInterface::SOURCE_CODE],
                'sku' => (string)$row[SourceItemInterface::SKU],
            ];
        }

        return $keys;
    }

    /**
     * Source code and SKU of every SKU in every given source
     *
     * @param array $skus
     * @param array $sourceCodes
     * @return array
     */
    public function forSkusInSources(array $skus, array $sourceCodes): array
    {
        $keys = [];
        foreach ($skus as $sku) {
            foreach ($sourceCodes as $sourceCode) {
                $keys[] = ['source_code' => (string)$sourceCode, 'sku' => (string)$sku];
            }
        }

        return $keys;
    }
}
