<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\Framework\Validation\ValidationResult;
use Magento\InventoryCatalogApi\Model\BulkInventoryTransferValidatorInterface;
use Magento\InventoryCatalog\Model\BulkInventoryTransfer;
use Magento\InventoryCatalog\Model\ResourceModel\BulkInventoryTransfer as BulkInventoryTransferResource;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;
use PHPUnit\Framework\TestCase;

class BulkInventoryTransferTest extends TestCase
{
    public function testReindexesTheTransferredSkusSoSalabilityChangesAreProcessed(): void
    {
        $validationResult = $this->createMock(ValidationResult::class);
        $validationResult->method('isValid')->willReturn(true);
        $validator = $this->createMock(BulkInventoryTransferValidatorInterface::class);
        $validator->method('validate')->willReturn($validationResult);
        $reindexSkusInSources = $this->createMock(ReindexSkusInSources::class);
        $model = new BulkInventoryTransfer(
            $validator,
            $this->createMock(BulkInventoryTransferResource::class),
            $reindexSkusInSources
        );

        $reindexSkusInSources->expects(self::once())->method('execute')->with(['SKU-1', 'SKU-2'], ['slr_a', 'slr_b']);

        $model->execute(['SKU-1', 'SKU-2'], 'slr_a', 'slr_b', false);
    }
}
