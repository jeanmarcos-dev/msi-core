<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryAdjustment\Plugin\InventoryImportExport;

use Magento\InventoryAdjustment\Model\AdjustmentRecorder;
use Magento\InventoryAdjustment\Model\SourceItemKeys;
use Magento\InventoryImportExport\Model\Import\Command\Replace;

class RecordReplacePlugin
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
     * Record the delete and save of a replaced bunch as its net change
     *
     * @param Replace $subject
     * @param callable $proceed
     * @param array $bunch
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(Replace $subject, callable $proceed, array $bunch): void
    {
        $this->recorder->recordNet($this->sourceItemKeys->fromRows($bunch), fn () => $proceed($bunch));
    }
}
