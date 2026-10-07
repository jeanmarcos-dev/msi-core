<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\Framework\Validation\ValidationResult;
use Magento\InventoryCatalogApi\Api\Data\PartialInventoryTransferItemInterface;
use Magento\InventoryCatalogApi\Model\PartialInventoryTransferValidatorInterface;
use Magento\InventoryCatalog\Model\BulkPartialInventoryTransfer;
use Magento\InventoryCatalog\Model\ResourceModel\TransferInventoryPartially;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;
use PHPUnit\Framework\TestCase;

class BulkPartialInventoryTransferTest extends TestCase
{
    public function testReindexesTheTransferredSkusSoSalabilityChangesAreProcessed(): void
    {
        $validationResult = $this->createMock(ValidationResult::class);
        $validationResult->method('isValid')->willReturn(true);
        $validator = $this->createMock(PartialInventoryTransferValidatorInterface::class);
        $validator->method('validate')->willReturn($validationResult);
        $reindexSkusInSources = $this->createMock(ReindexSkusInSources::class);
        $model = new BulkPartialInventoryTransfer(
            $validator,
            $this->createMock(TransferInventoryPartially::class),
            $reindexSkusInSources
        );
        $items = [];
        foreach (['SKU-1', 'SKU-2', 'SKU-1'] as $sku) {
            $item = $this->createMock(PartialInventoryTransferItemInterface::class);
            $item->method('getSku')->willReturn($sku);
            $items[] = $item;
        }

        $reindexSkusInSources->expects(self::once())->method('execute')
            ->with(['SKU-1', 'SKU-2', 'SKU-1'], ['slr_a', 'slr_b']);

        $model->execute('slr_a', 'slr_b', $items);
    }
}
