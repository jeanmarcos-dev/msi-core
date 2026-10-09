<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryCatalog;

use Magento\InventoryAdjustment\Model\AdjustmentMetadata;
use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;
use Magento\InventoryAdjustmentApi\Api\AdjustmentContextInterface;
use Magento\InventoryAdjustmentApi\Model\AdjustmentReason;
use Magento\InventoryCatalog\Model\ResourceModel\BulkSourceUnassign;

class RecordBulkSourceUnassignPlugin
{
    /**
     * @param AdjustmentRecorder $recorder
     * @param SourceItemKeys $sourceItemKeys
     * @param AdjustmentContextInterface $context
     */
    public function __construct(
        private readonly AdjustmentRecorder $recorder,
        private readonly SourceItemKeys $sourceItemKeys,
        private readonly AdjustmentContextInterface $context
    ) {
    }

    /**
     * Record the source items a bulk unassign removes
     *
     * @param BulkSourceUnassign $subject
     * @param callable $proceed
     * @param array $skus
     * @param array $sourceCodes
     * @return int
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        BulkSourceUnassign $subject,
        callable $proceed,
        array $skus,
        array $sourceCodes
    ): int {
        return $this->context->run(
            new AdjustmentMetadata(AdjustmentReason::Other),
            fn () => $this->recorder->record(
                $this->sourceItemKeys->forSkusInSources($skus, $sourceCodes),
                fn () => $proceed($skus, $sourceCodes)
            )
        );
    }
}
