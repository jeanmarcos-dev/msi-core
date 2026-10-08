<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\Inventory;

use Magento\Inventory\Model\ResourceModel\SourceItem\SaveMultiple;
use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;

class RecordSaveMultiplePlugin
{
    /**
     * @param AdjustmentRecorder $recorder
     * @param SourceItemKeys $sourceItemKeys
     */
    public function __construct(
        private readonly AdjustmentRecorder $recorder,
        private readonly SourceItemKeys $sourceItemKeys
    ) {
    }

    /**
     * Record the source items a save changes
     *
     * @param SaveMultiple $subject
     * @param callable $proceed
     * @param array $sourceItems
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(SaveMultiple $subject, callable $proceed, array $sourceItems): mixed
    {
        return $this->recorder->record(
            $this->sourceItemKeys->fromSourceItems($sourceItems),
            fn () => $proceed($sourceItems)
        );
    }
}
