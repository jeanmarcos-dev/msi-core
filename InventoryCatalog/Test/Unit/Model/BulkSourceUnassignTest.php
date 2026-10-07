<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\Framework\Validation\ValidationResult;
use Magento\InventoryCatalogApi\Model\BulkSourceUnassignValidatorInterface;
use Magento\InventoryCatalog\Model\BulkSourceUnassign;
use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceUnassign as BulkSourceUnassignResource;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;
use PHPUnit\Framework\TestCase;

class BulkSourceUnassignTest extends TestCase
{
    public function testReindexesTheUnassignedSkusSoSalabilityChangesAreProcessed(): void
    {
        $validationResult = $this->createMock(ValidationResult::class);
        $validationResult->method('isValid')->willReturn(true);
        $validator = $this->createMock(BulkSourceUnassignValidatorInterface::class);
        $validator->method('validate')->willReturn($validationResult);
        $reindexSkusInSources = $this->createMock(ReindexSkusInSources::class);
        $model = new BulkSourceUnassign($validator, $this->createMock(BulkSourceUnassignResource::class), $reindexSkusInSources);

        $reindexSkusInSources->expects(self::once())->method('execute')->with(['SKU-1'], ['slr_a', 'slr_b']);

        $model->execute(['SKU-1'], ['slr_a', 'slr_b']);
    }
}
