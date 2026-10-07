<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Test\Unit\Model;

use Magento\Framework\Validation\ValidationResult;
use Magento\InventoryCatalogApi\Model\BulkSourceAssignValidatorInterface;
use Magento\InventoryCatalog\Model\BulkSourceAssign;
use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceAssign as BulkSourceAssignResource;
use Magento\InventoryIndexer\Indexer\SourceItem\ReindexSkusInSources;
use PHPUnit\Framework\TestCase;

class BulkSourceAssignTest extends TestCase
{
    public function testReindexesTheAssignedSkusInsteadOfPassingSourceCodesAsItemIds(): void
    {
        $validationResult = $this->createMock(ValidationResult::class);
        $validationResult->method('isValid')->willReturn(true);
        $validator = $this->createMock(BulkSourceAssignValidatorInterface::class);
        $validator->method('validate')->willReturn($validationResult);
        $reindexSkusInSources = $this->createMock(ReindexSkusInSources::class);
        $model = new BulkSourceAssign(
            $validator,
            $this->createMock(BulkSourceAssignResource::class),
            $reindexSkusInSources
        );

        $reindexSkusInSources->expects(self::once())->method('execute')->with(['SKU-1'], ['slr_a']);

        $model->execute(['SKU-1'], ['slr_a']);
    }
}
