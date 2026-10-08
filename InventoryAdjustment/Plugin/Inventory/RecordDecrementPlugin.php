<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\Inventory;

use Magento\Inventory\Model\ResourceModel\SourceItem\DecrementQtyForMultipleSourceItem;
use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;

class RecordDecrementPlugin
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
     * Record the source items a deduction changes
     *
     * @param DecrementQtyForMultipleSourceItem $subject
     * @param callable $proceed
     * @param array $decrementItems
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        DecrementQtyForMultipleSourceItem $subject,
        callable $proceed,
        array $decrementItems
    ): mixed {
        return $this->recorder->record(
            $this->sourceItemKeys->fromSourceItems(array_column($decrementItems, 'source_item')),
            fn () => $proceed($decrementItems)
        );
    }
}
